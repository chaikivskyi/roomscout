<?php

namespace App\Tests\Application\Placement;

use App\Placement\Entity\ProductPlacement;
use App\Placement\Enum\PlacementStatus;
use App\Placement\Message\GeneratePlacementImageMessage;
use App\Placement\MessageHandler\GeneratePlacementImageHandler;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductPlacementFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class GeneratePlacementImageHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';
    private const string RESULT_PNG = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

    protected function tearDown(): void
    {
        foreach (['project.storage', 'product_thumbnails.storage'] as $storageId) {
            $storage = static::getContainer()->get($storageId);
            \assert($storage instanceof FilesystemOperator);
            foreach ($storage->listContents('')->toArray() as $item) {
                $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
            }
        }

        parent::tearDown();
    }

    public function testComposesAndStoresTheResultImage(): void
    {
        $requests = [];
        $this->mockGemini(function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = ['method' => $method, 'url' => $url, 'body' => $options['body'] ?? ''];

            return self::imageResponse();
        });

        $placement = $this->placementWithAssets('put the table under the window');

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        self::assertSame(PlacementStatus::Completed, $placement->getStatus());

        // The result was appended as the project's new latest image version.
        $resultVersion = $placement->getResultVersion();
        self::assertNotNull($resultVersion);
        $latest = static::getContainer()->get(ProjectImageVersionRepository::class)
            ->findLatestForProject($placement->getProject()->getId());
        self::assertNotNull($latest);
        self::assertTrue($resultVersion->getId()->equals($latest->getId()));
        self::assertStringEndsWith('/image.png', $resultVersion->getImagePath());
        self::assertSame(base64_decode(self::RESULT_PNG), $this->projectStorage()->read($resultVersion->getImagePath()));
        self::assertGreaterThan(
            $placement->getCreatedAt(),
            $placement->getUpdatedAt(),
            'PreUpdate must advance updatedAt on the processing → completed transition.',
        );

        self::assertCount(1, $requests);
        self::assertSame('POST', $requests[0]['method']);
        self::assertSame(
            'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-image:generateContent',
            $requests[0]['url'],
        );

        $parts = self::sentParts($requests[0]['body']);
        self::assertCount(3, $parts);
        self::assertStringContainsString('put the table under the window', $parts[0]['text'] ?? '');
        self::assertNotEmpty($parts[1]['inlineData']['data'] ?? '');
        self::assertNotEmpty($parts[2]['inlineData']['data'] ?? '');
    }

    public function testGeneratedFileIsRemovedWhenTheFlushFails(): void
    {
        $this->mockGemini([self::imageResponse()]);

        $placement = $this->placementWithAssets();
        $filesBefore = $this->projectStorageFiles();

        $this->entityManager()->getEventManager()->addEventListener(Events::onFlush, new class {
            public function onFlush(): void
            {
                throw new \RuntimeException('flush boom');
            }
        });

        try {
            $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
            self::fail('Expected the flush failure to bubble.');
        } catch (\RuntimeException $e) {
            self::assertSame('flush boom', $e->getMessage(), 'The original flush error must not be masked by cleanup.');
        }

        self::assertSame($filesBefore, $this->projectStorageFiles(), 'The generated file must not be orphaned when the flush fails.');
    }

    public function testTerminalPlacementIsSkipped(): void
    {
        $client = $this->mockGemini([]);

        $placement = ProductPlacementFactory::new()->completed()->create();

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertSame(PlacementStatus::Completed, $placement->getStatus());
    }

    public function testDeletedPlacementIsSkippedWithoutRetry(): void
    {
        $client = $this->mockGemini([]);

        $this->handler()(new GeneratePlacementImageMessage(Uuid::v7()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testMalformedPlacementIdIsNotRetried(): void
    {
        $client = $this->mockGemini([]);

        try {
            $this->handler()(new GeneratePlacementImageMessage('999999'));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('999999', $e->getMessage());
        }

        self::assertSame(0, $client->getRequestsCount());
    }

    public function testProjectWithoutAnyImageVersionFailsThePlacement(): void
    {
        $client = $this->mockGemini([]);

        // The factory creates no image version for the project.
        $placement = ProductPlacementFactory::createOne();

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testMissingProjectImageFileFailsThePlacement(): void
    {
        $client = $this->mockGemini([]);

        $placement = ProductPlacementFactory::createOne();
        // A version row exists but its file was never written to storage.
        ProjectImageVersionFactory::createOne(['project' => $placement->getProject()]);

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testMissingProductThumbnailFailsThePlacement(): void
    {
        $client = $this->mockGemini([]);

        $placement = ProductPlacementFactory::createOne([
            'product' => ProductFactory::new(['thumbnailUrl' => 'gone/thumbnail.png']),
        ]);
        ProjectImageVersionFactory::createOne(['project' => $placement->getProject(), 'imagePath' => 'room-nothumb/image.png']);
        $this->projectStorage()->write('room-nothumb/image.png', base64_decode(self::PNG_1X1));

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testDeletedProductFailsThePlacement(): void
    {
        $client = $this->mockGemini([]);

        $placement = $this->placementWithAssets();
        $placementId = $placement->getId();

        $entityManager = $this->entityManager();
        $product = $placement->getProduct();
        self::assertNotNull($product);
        $entityManager->remove($product);
        $entityManager->flush();
        $entityManager->clear();

        $this->handler()(new GeneratePlacementImageMessage($placementId->toRfc4122()));

        self::assertSame(0, $client->getRequestsCount());
        $reloaded = $entityManager->find(ProductPlacement::class, $placementId);
        self::assertNotNull($reloaded);
        self::assertSame(PlacementStatus::Failed, $reloaded->getStatus());
        self::assertNull($reloaded->getProduct());
    }

    public function testClientErrorIsUnrecoverable(): void
    {
        $this->mockGemini([new MockResponse('invalid request', ['http_code' => 400])]);

        $placement = $this->placementWithAssets();

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('invalid request');
        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
    }

    public function testResponseWithoutImagePartIsUnrecoverable(): void
    {
        $this->mockGemini([new MockResponse(json_encode([
            'candidates' => [['content' => ['parts' => [['text' => 'I cannot help with that.']]]]],
        ], JSON_THROW_ON_ERROR))]);

        $placement = $this->placementWithAssets();

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('no image part');
        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
    }

    public function testUnmappedImageMimeTypeFallsBackToSubtypeExtension(): void
    {
        $this->mockGemini([new MockResponse(json_encode([
            'candidates' => [['content' => ['parts' => [
                ['inlineData' => ['mimeType' => 'image/tiff', 'data' => self::RESULT_PNG]],
            ]]]],
        ], JSON_THROW_ON_ERROR))]);

        $placement = $this->placementWithAssets();

        $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));

        // The bytes were already paid for — an unmapped image format must be
        // stored (extension from the mime subtype), not discarded and retried.
        self::assertSame(PlacementStatus::Completed, $placement->getStatus());
        $resultVersion = $placement->getResultVersion();
        self::assertNotNull($resultVersion);
        self::assertStringEndsWith('/image.tiff', $resultVersion->getImagePath());
    }

    public function testNonImageResultMimeTypeIsRetryable(): void
    {
        $this->mockGemini([new MockResponse(json_encode([
            'candidates' => [['content' => ['parts' => [
                ['inlineData' => ['mimeType' => 'image/svg+xml', 'data' => self::RESULT_PNG]],
            ]]]],
        ], JSON_THROW_ON_ERROR))]);

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\UnexpectedValueException $e) {
            // Gemini output varies per call, so a retry can legitimately return
            // a storable format; final failure is handled by the listener.
            self::assertStringContainsString('image/svg+xml', $e->getMessage());
        }

        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
        self::assertNull($placement->getResultVersion());
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->mockGemini([new MockResponse('slow down', ['http_code' => 429, 'response_headers' => ['retry-after' => '7']])]);

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry(), 'Retries must stay bounded by the transport retry strategy.');
        }

        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
    }

    public function testServerErrorIsRetryable(): void
    {
        $this->mockGemini([new MockResponse('boom', ['http_code' => 500])]);

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImageMessage($placement->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('boom', $e->getMessage());
        }
    }

    private function placementWithAssets(?string $prompt = null): ProductPlacement
    {
        $roomPath = Uuid::v7()->toRfc4122().'/image.png';
        $thumbnailPath = Uuid::v7()->toRfc4122().'/thumbnail.png';

        $contextAttributes = [];

        if (null !== $prompt) {
            $contextAttributes['prompt'] = $prompt;
        }

        $placement = ProductPlacementFactory::createOne([
            'context' => ProjectContextFactory::new($contextAttributes)->completed(),
            'product' => ProductFactory::new(['thumbnailUrl' => $thumbnailPath]),
        ]);
        ProjectImageVersionFactory::createOne(['project' => $placement->getProject(), 'imagePath' => $roomPath]);

        $this->projectStorage()->write($roomPath, base64_decode(self::PNG_1X1));
        $this->thumbnailStorage()->write($thumbnailPath, base64_decode(self::PNG_1X1));

        return $placement;
    }

    private static function imageResponse(): MockResponse
    {
        return new MockResponse(json_encode([
            'candidates' => [['content' => ['parts' => [
                ['text' => 'Here is the edited room.'],
                ['inlineData' => ['mimeType' => 'image/png', 'data' => self::RESULT_PNG]],
            ]]]],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param callable|list<MockResponse> $responseFactory
     */
    private function mockGemini(callable|array $responseFactory): MockHttpClient
    {
        $client = new MockHttpClient($responseFactory, 'https://generativelanguage.googleapis.com');
        static::getContainer()->set('gemini.client', $client);

        return $client;
    }

    private function handler(): GeneratePlacementImageHandler
    {
        return static::getContainer()->get(GeneratePlacementImageHandler::class);
    }

    private function projectStorage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }

    /**
     * @return list<string>
     */
    private function projectStorageFiles(): array
    {
        $paths = [];
        foreach ($this->projectStorage()->listContents('', true)->toArray() as $item) {
            if (!$item->isDir()) {
                $paths[] = $item->path();
            }
        }
        sort($paths);

        return $paths;
    }

    private function thumbnailStorage(): FilesystemOperator
    {
        return static::getContainer()->get('product_thumbnails.storage');
    }

    /**
     * @return list<array{text?: string, inlineData?: array{mimeType?: string, data?: string}}>
     */
    private static function sentParts(mixed $body): array
    {
        self::assertIsString($body);
        /** @var array{contents?: list<array{parts?: list<array{text?: string, inlineData?: array{mimeType?: string, data?: string}}>}>} $decoded */
        $decoded = json_decode($body, true);

        return $decoded['contents'][0]['parts'] ?? [];
    }
}
