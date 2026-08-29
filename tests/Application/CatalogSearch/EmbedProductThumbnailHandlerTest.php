<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Command\EmbedProductThumbnail;
use App\CatalogSearch\Command\EmbedProductThumbnailHandler;
use App\CatalogSearch\Entity\ProductEmbedding;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
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
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return self::embeddingResponse();
        });

        $product = ProductFactory::createOne(['thumbnailUrl' => 'e2e-test/thumbnail.png']);
        $this->storage()->write('e2e-test/thumbnail.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        $embedding = $this->findEmbedding($product->getId());
        self::assertNotNull($embedding);
        self::assertSame('embed-v4.0', $embedding->getModel());
        self::assertSame(hash('sha256', base64_decode(self::PNG_1X1)), $embedding->getSourceThumbnailHash());
        self::assertCount(1536, $embedding->getEmbedding()->toArray());

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame('https://api.cohere.com/v2/embed', $requests[0]['url']);
        $body = self::decodeRequest($requests[0]['body']);
        self::assertSame('embed-v4.0', $body['model'] ?? null);
        self::assertSame('search_document', $body['input_type'] ?? null);
        self::assertSame(1536, $body['output_dimension'] ?? null);
        self::assertStringStartsWith('data:image/png;base64,', self::sentImageUrl($body));
    }

    public function testHandlesAdminStyleFlatThumbnailKey(): void
    {
        $this->mockCohere([self::embeddingResponse()]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'a1b2c3d4.png']);
        $this->storage()->write('a1b2c3d4.png', base64_decode(self::PNG_1X1));

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertNotNull($this->findEmbedding($product->getId()));
    }

    public function testOversizedThumbnailIsDownscaledToJpegBeforeSending(): void
    {
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['body'] ?? '';

            return self::embeddingResponse();
        });

        $image = imagecreatetruecolor(2048, 1024);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 120, 40, 200));
        ob_start();
        imagepng($image);
        $bytes = (string) ob_get_clean();
        imagedestroy($image);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'oversized/thumbnail.png']);
        $this->storage()->write('oversized/thumbnail.png', $bytes);

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        $url = self::sentImageUrl(self::decodeRequest($requests[0]));
        self::assertStringStartsWith('data:image/jpeg;base64,', $url);

        $dimensions = getimagesizefromstring(base64_decode(substr($url, \strlen('data:image/jpeg;base64,'))));
        self::assertNotFalse($dimensions);
        self::assertSame(1536, $dimensions[0]);
        self::assertSame(768, $dimensions[1]);
    }

    public function testDeclaredDecompressionBombIsSentUndecoded(): void
    {
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['body'] ?? '';

            return self::embeddingResponse();
        });

        $bytes = base64_decode(self::PNG_1X1);
        $bytes = substr_replace($bytes, pack('N', 100000), 16, 4);
        $bytes = substr_replace($bytes, pack('N', 100000), 20, 4);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'bomb/thumbnail.png']);
        $this->storage()->write('bomb/thumbnail.png', $bytes);

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertSame(
            'data:image/png;base64,'.base64_encode($bytes),
            self::sentImageUrl(self::decodeRequest($requests[0])),
        );
    }

    public function testUndecodableThumbnailBytesAreSentAsIs(): void
    {
        $requests = [];
        $this->mockCohere(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = $options['body'] ?? '';

            return self::embeddingResponse();
        });

        $bytes = str_repeat('not an image at all ', 300000);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'garbage/thumbnail.png']);
        $this->storage()->write('garbage/thumbnail.png', $bytes);

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertStringEndsWith(
            ';base64,'.base64_encode($bytes),
            self::sentImageUrl(self::decodeRequest($requests[0])),
        );
    }

    public function testSecondInvocationIsIdempotent(): void
    {
        $client = $this->mockCohere([self::embeddingResponse()]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'idempotent/thumbnail.png']);
        $this->storage()->write('idempotent/thumbnail.png', base64_decode(self::PNG_1X1));

        $handler = $this->handler();
        $handler(new EmbedProductThumbnail($product->getId()->toRfc4122()));
        $handler(new EmbedProductThumbnail($product->getId()->toRfc4122()));

        self::assertSame(1, $client->getRequestsCount());
        self::assertSame(1, $this->entityManager()->getRepository(ProductEmbedding::class)->count(['product' => $product->getId()]));
    }

    public function testDeletedProductIsSkippedWithoutRetries(): void
    {
        $client = $this->mockCohere([]);
        $unknownId = Uuid::v7();

        $this->handler()(new EmbedProductThumbnail($unknownId->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertNull($this->findEmbedding($unknownId));
    }

    public function testMalformedProductIdIsNotRetried(): void
    {
        $client = $this->mockCohere([]);

        try {
            $this->handler()(new EmbedProductThumbnail('999999'));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('999999', $e->getMessage());
        }

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testSkipsWhenThumbnailIsMissingFromStorage(): void
    {
        $client = $this->mockCohere([]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'missing/thumbnail.png']);

        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));

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
        $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->mockCohere([new MockResponse('slow down', ['http_code' => 429, 'response_headers' => ['retry-after' => '7']])]);

        $product = ProductFactory::createOne(['thumbnailUrl' => 'err429/thumbnail.png']);
        $this->storage()->write('err429/thumbnail.png', base64_decode(self::PNG_1X1));

        try {
            $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
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
            $this->handler()(new EmbedProductThumbnail($product->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('boom', $e->getMessage());
        }
    }

    private static function embeddingResponse(): MockResponse
    {
        return new MockResponse(json_encode(['embeddings' => ['float' => [array_fill(0, 1536, 0.25)]]], JSON_THROW_ON_ERROR));
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

    /**
     * @return array{model?: string, input_type?: string, output_dimension?: int, inputs?: list<array{content: list<array{image_url: array{url: string}}>}>}
     */
    private static function decodeRequest(mixed $body): array
    {
        self::assertIsString($body);
        /** @var array{model?: string, input_type?: string, output_dimension?: int, inputs?: list<array{content: list<array{image_url: array{url: string}}>}>} $decoded */
        $decoded = json_decode($body, true);

        return $decoded;
    }

    /**
     * @param array{inputs?: list<array{content: list<array{image_url: array{url: string}}>}>} $request
     */
    private static function sentImageUrl(array $request): string
    {
        return $request['inputs'][0]['content'][0]['image_url']['url'] ?? self::fail('No image url in the captured request.');
    }
}
