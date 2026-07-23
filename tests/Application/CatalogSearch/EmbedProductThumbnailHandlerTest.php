<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Entity\ProductEmbedding;
use App\CatalogSearch\Message\EmbedProductThumbnailMessage;
use App\CatalogSearch\MessageHandler\EmbedProductThumbnailHandler;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\StorageAttributes;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;

final class EmbedProductThumbnailHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            \assert($item instanceof StorageAttributes);
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testEmbedsThumbnailAndStoresVector(): void
    {
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return self::embeddingResponse();
        });

        $product = ProductFactory::createOne(['thumbnailUrl' => 'e2e-test/thumbnail.png']);
        $this->storage()->write('e2e-test/thumbnail.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnailMessage($product->getId()));

        $embedding = $this->findEmbedding($product->getId());
        self::assertNotNull($embedding);
        self::assertSame('embed-v4.0', $embedding->getModel());
        self::assertSame(hash('sha256', base64_decode(self::PNG_1X1)), $embedding->getSourceThumbnailHash());
        self::assertCount(1536, $embedding->getEmbedding()->toArray());

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://api.cohere.com/v2/embed', $requests[0]['url']);
        $body = json_decode($requests[0]['body'], true);
        self::assertSame('embed-v4.0', $body['model']);
        self::assertSame('search_document', $body['input_type']);
        self::assertSame(1536, $body['output_dimension']);
        self::assertStringStartsWith('data:image/png;base64,', $body['inputs'][0]['content'][0]['image_url']['url']);
    }

    public function testHandlesAdminStyleFlatThumbnailKey(): void
    {
        $this->mockCohere([self::embeddingResponse()]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'a1b2c3d4.png']);
        $this->storage()->write('a1b2c3d4.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnailMessage($product->getId()));

        self::assertNotNull($this->findEmbedding($product->getId()));
    }

    public function testSecondInvocationIsIdempotent(): void
    {
        $client = $this->mockCohere([self::embeddingResponse()]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'idempotent/thumbnail.png']);
        $this->storage()->write('idempotent/thumbnail.png', base64_decode(self::PNG_1X1));

        $handler = $this->handler();
        $handler(new EmbedProductThumbnailMessage($product->getId()));
        $handler(new EmbedProductThumbnailMessage($product->getId()));

        self::assertSame(1, $client->getRequestsCount());
        self::assertSame(1, $this->entityManager()->getRepository(ProductEmbedding::class)->count(['product' => $product->getId()]));
    }

    public function testMissingProductIsRetriedWithBoundedRetries(): void
    {
        $client = $this->mockCohere([]);

        try {
            $this->handler()(new EmbedProductThumbnailMessage(999999));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertFalse($e->forceRetry(), 'Retries must stay bounded by the transport retry strategy.');
        }

        self::assertSame(0, $client->getRequestsCount());
        self::assertNull($this->findEmbedding(999999));
    }

    public function testSkipsWhenThumbnailIsMissingFromStorage(): void
    {
        $client = $this->mockCohere([]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'missing/thumbnail.png']);

        $this->handler()(new EmbedProductThumbnailMessage($product->getId()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertNull($this->findEmbedding($product->getId()));
    }

    public function testClientErrorIsUnrecoverable(): void
    {
        $this->mockCohere([new MockResponse('invalid request', ['http_code' => 400])]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'err400/thumbnail.png']);
        $this->storage()->write('err400/thumbnail.png', base64_decode(self::PNG_1X1));

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('invalid request');
        $this->handler()(new EmbedProductThumbnailMessage($product->getId()));
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->mockCohere([new MockResponse('slow down', ['http_code' => 429, 'response_headers' => ['retry-after' => '7']])]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'err429/thumbnail.png']);
        $this->storage()->write('err429/thumbnail.png', base64_decode(self::PNG_1X1));

        try {
            $this->handler()(new EmbedProductThumbnailMessage($product->getId()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry());
        }
    }

    public function testServerErrorIsRetryable(): void
    {
        $this->mockCohere([new MockResponse('boom', ['http_code' => 500])]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'err500/thumbnail.png']);
        $this->storage()->write('err500/thumbnail.png', base64_decode(self::PNG_1X1));

        try {
            $this->handler()(new EmbedProductThumbnailMessage($product->getId()));
            self::fail('Expected a retryable exception.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('boom', $e->getMessage());
        }
    }

    private static function embeddingResponse(): MockResponse
    {
        return new MockResponse(json_encode(['embeddings' => ['float' => [array_fill(0, 1536, 0.25)]]]));
    }

    /**
     * @param callable|list<MockResponse> $responseFactory
     */
    private function mockCohere(callable|array $responseFactory): MockHttpClient
    {
        $client = new MockHttpClient($responseFactory, 'https://api.cohere.com');
        static::getContainer()->set('cohere.client', $client);

        return $client;
    }

    private function handler(): EmbedProductThumbnailHandler
    {
        $handler = static::getContainer()->get(EmbedProductThumbnailHandler::class);
        \assert($handler instanceof EmbedProductThumbnailHandler);

        return $handler;
    }

    private function findEmbedding(int $productId): ?ProductEmbedding
    {
        return $this->entityManager()->getRepository(ProductEmbedding::class)
            ->findOneBy(['product' => $productId]);
    }

    private function storage(): FilesystemOperator
    {
        $storage = static::getContainer()->get('product_thumbnails.storage');
        \assert($storage instanceof FilesystemOperator);

        return $storage;
    }
}
