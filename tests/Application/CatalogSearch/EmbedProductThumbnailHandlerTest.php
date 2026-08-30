<?php

namespace App\Tests\Application\CatalogSearch;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Command\EmbedProductThumbnail;
use App\CatalogSearch\Command\EmbedProductThumbnailHandler;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Exception\EmbeddingRateLimitedException;
use App\CatalogSearch\Exception\EmbeddingRejectedException;
use App\CatalogSearch\Exception\EmbeddingUnavailableException;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Fake\FakeImageEmbedder;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class EmbedProductThumbnailHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testEmbedsThumbnailAndStoresVector(): void
    {
        $embedder = $this->embedder();

        $product = ProductFactory::createOne(['thumbnailUrl' => 'e2e-test/thumbnail.png']);
        $this->storage()->write('e2e-test/thumbnail.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        $embedding = $this->findEmbedding($product->getId());
        self::assertNotNull($embedding);
        self::assertSame(FakeImageEmbedder::MODEL, $embedding->getModel());
        self::assertSame(hash('sha256', base64_decode(self::PNG_1X1)), $embedding->getSourceThumbnailHash());
        self::assertCount(ProductEmbedding::DIMENSIONS, $embedding->getEmbedding()->toArray());

        $calls = $embedder->calls();
        self::assertCount(1, $calls);
        self::assertSame('image', $calls[0]['type'], 'A product thumbnail is a search document, not a query.');
        self::assertSame('image/png', $calls[0]['mimeType']);
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['bytes'], 'The bytes hashed into sourceThumbnailHash must be the bytes embedded.');
    }

    public function testHandlesAdminStyleFlatThumbnailKey(): void
    {
        $product = ProductFactory::createOne(['thumbnailUrl' => 'a1b2c3d4.png']);
        $this->storage()->write('a1b2c3d4.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertNotNull($this->findEmbedding($product->getId()));
    }

    public function testSecondInvocationIsIdempotent(): void
    {
        $embedder = $this->embedder();

        $product = ProductFactory::createOne(['thumbnailUrl' => 'idempotent/thumbnail.png']);
        $this->storage()->write('idempotent/thumbnail.png', base64_decode(self::PNG_1X1));

        $handler = $this->handler();
        $handler(new EmbedProductThumbnail($product->getId()->toRfc4122()));
        $handler(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertSame(1, $embedder->callCount());
        self::assertSame(1, $this->entityManager()->getRepository(ProductEmbedding::class)->count(['product' => $product->getId()]));
    }

    public function testDeletedProductIsSkippedWithoutRetries(): void
    {
        $embedder = $this->embedder();
        $unknownId = Uuid::v7();

        $this->handler()(new EmbedProductThumbnail($unknownId->toRfc4122()));

        self::assertSame(0, $embedder->callCount());
        self::assertNull($this->findEmbedding($unknownId));
    }

    public function testMalformedProductIdIsNotRetried(): void
    {
        $embedder = $this->embedder();

        try {
            $this->handler()(new EmbedProductThumbnail('999999'));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('999999', $e->getMessage());
        }

        self::assertSame(0, $embedder->callCount());
    }

    public function testSkipsWhenThumbnailIsMissingFromStorage(): void
    {
        $embedder = $this->embedder();

        $product = ProductFactory::createOne(['thumbnailUrl' => 'missing/thumbnail.png']);

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertSame(0, $embedder->callCount());
        self::assertNull($this->findEmbedding($product->getId()));
    }

    public function testRejectedEmbeddingIsUnrecoverable(): void
    {
        $this->embedder()->willThrow(new EmbeddingRejectedException('invalid request'));

        $product = $this->productWithThumbnail('rejected');

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('invalid request');
        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->embedder()->willThrow(new EmbeddingRateLimitedException('slow down', 7000));

        $product = $this->productWithThumbnail('ratelimited');

        try {
            $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry());
        }
    }

    public function testUnavailableProviderIsRetryable(): void
    {
        $this->embedder()->willThrow(new EmbeddingUnavailableException('boom'));

        $product = $this->productWithThumbnail('unavailable');

        try {
            $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('boom', $e->getMessage());
        }
    }

    private function productWithThumbnail(string $directory): Product
    {
        $path = $directory.'/thumbnail.png';
        $product = ProductFactory::createOne(['thumbnailUrl' => $path]);
        $this->storage()->write($path, base64_decode(self::PNG_1X1));

        return $product;
    }

    private function embedder(): FakeImageEmbedder
    {
        return static::getContainer()->get(FakeImageEmbedder::class);
    }

    private function handler(): EmbedProductThumbnailHandler
    {
        return static::getContainer()->get(EmbedProductThumbnailHandler::class);
    }

    private function findEmbedding(Uuid $productId): ?ProductEmbedding
    {
        return $this->entityManager()->getRepository(ProductEmbedding::class)
            ->findOneBy(['product' => $productId]);
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('product_thumbnails.storage');
    }
}
