<?php

namespace App\Tests\Application\Placement;

use App\Placement\Command\GeneratePlacementImage;
use App\Placement\Command\GeneratePlacementImageHandler;
use App\Placement\Dto\ComposedImage;
use App\Placement\Entity\ProductPlacement;
use App\Placement\Enum\PlacementStatus;
use App\Placement\Exception\ImageGenerationRateLimitedException;
use App\Placement\Exception\ImageGenerationRejectedException;
use App\Placement\Exception\ImageGenerationUnavailableException;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductPlacementFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Fake\FakeProductImageComposer;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class GeneratePlacementImageHandlerTest extends ApiTestCase
{
    private const string PNG_1X1 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==';

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
        $composer = $this->composer();

        $placement = $this->placementWithAssets('put the table under the window');

        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));

        self::assertSame(PlacementStatus::Completed, $placement->getStatus());

        $resultVersion = $placement->getResultVersion();
        self::assertNotNull($resultVersion);
        $latest = static::getContainer()->get(ProjectImageVersionRepository::class)
            ->findLatestForProject($placement->getProject()->getId());
        self::assertNotNull($latest);
        self::assertTrue($resultVersion->getId()->equals($latest->getId()));
        self::assertStringEndsWith('/image.png', $resultVersion->getImagePath());
        self::assertSame(FakeProductImageComposer::defaultImage()->bytes, $this->projectStorage()->read($resultVersion->getImagePath()));
        self::assertGreaterThan(
            $placement->getCreatedAt(),
            $placement->getUpdatedAt(),
            'PreUpdate must advance updatedAt on the processing → completed transition.',
        );

        $calls = $composer->calls();
        self::assertCount(1, $calls);
        self::assertSame('put the table under the window', $calls[0]['prompt'], 'The handler passes the prompt copied onto the placement row.');
        self::assertSame('image/png', $calls[0]['roomMimeType']);
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['roomBytes']);
        self::assertSame('image/png', $calls[0]['productMimeType']);
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['productBytes']);
    }

    public function testGeneratedFileIsRemovedWhenTheFlushFails(): void
    {
        $placement = $this->placementWithAssets();
        $filesBefore = $this->projectStorageFiles();

        $this->entityManager()->getEventManager()->addEventListener(Events::onFlush, new class {
            public function onFlush(): void
            {
                throw new \RuntimeException('flush boom');
            }
        });

        try {
            $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
            self::fail('Expected the flush failure to bubble.');
        } catch (\RuntimeException $e) {
            self::assertSame('flush boom', $e->getMessage(), 'The original flush error must not be masked by cleanup.');
        }

        self::assertSame($filesBefore, $this->projectStorageFiles(), 'The generated file must not be orphaned when the flush fails.');
    }

    public function testTerminalPlacementIsSkipped(): void
    {
        $composer = $this->composer();

        $placement = ProductPlacementFactory::new()->completed()->create();

        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(PlacementStatus::Completed, $placement->getStatus());
    }

    public function testDeletedPlacementIsSkippedWithoutRetry(): void
    {
        $composer = $this->composer();

        $this->handler()(new GeneratePlacementImage(Uuid::v7()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
    }

    public function testMalformedPlacementIdIsNotRetried(): void
    {
        $composer = $this->composer();

        try {
            $this->handler()(new GeneratePlacementImage('999999'));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('999999', $e->getMessage());
        }

        self::assertSame(0, $composer->callCount());
    }

    public function testProjectWithoutAnyImageVersionFailsThePlacement(): void
    {
        $composer = $this->composer();

        $placement = ProductPlacementFactory::createOne();

        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testMissingProjectImageFileFailsThePlacement(): void
    {
        $composer = $this->composer();

        $placement = ProductPlacementFactory::createOne();
        ProjectImageVersionFactory::createOne(['project' => $placement->getProject()]);

        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testMissingProductThumbnailFailsThePlacement(): void
    {
        $composer = $this->composer();

        $placement = ProductPlacementFactory::createOne([
            'product' => ProductFactory::new(['thumbnailUrl' => 'gone/thumbnail.png']),
        ]);
        ProjectImageVersionFactory::createOne(['project' => $placement->getProject(), 'imagePath' => 'room-nothumb/image.png']);
        $this->projectStorage()->write('room-nothumb/image.png', base64_decode(self::PNG_1X1));

        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testDeletedProductFailsThePlacement(): void
    {
        $composer = $this->composer();

        $placement = $this->placementWithAssets();
        $placementId = $placement->getId();

        $entityManager = $this->entityManager();
        $product = $placement->getProduct();
        self::assertNotNull($product);
        $entityManager->remove($product);
        $entityManager->flush();
        $entityManager->clear();

        $this->handler()(new GeneratePlacementImage($placementId->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        $reloaded = $entityManager->find(ProductPlacement::class, $placementId);
        self::assertNotNull($reloaded);
        self::assertSame(PlacementStatus::Failed, $reloaded->getStatus());
        self::assertNull($reloaded->getProduct());
    }

    public function testRejectedGenerationIsUnrecoverable(): void
    {
        $this->composer()->willThrow(new ImageGenerationRejectedException('invalid request'));

        $placement = $this->placementWithAssets();

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('invalid request');
        $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
    }

    public function testUnmappedImageMimeTypeIsNeverWrittenToStorage(): void
    {
        $this->composer()->willReturn(new ComposedImage('image/php', FakeProductImageComposer::defaultImage()->bytes));

        $placement = $this->placementWithAssets();
        $filesBefore = $this->projectStorageFiles();

        try {
            $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
            self::fail('Expected an unsupported mime type to be rejected.');
        } catch (\UnexpectedValueException $e) {
            self::assertStringContainsString('image/php', $e->getMessage());
        }

        self::assertSame($filesBefore, $this->projectStorageFiles(), 'A response-controlled extension must never reach the web-served directory.');
        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
        self::assertNull($placement->getResultVersion());
    }

    public function testNonImageResultMimeTypeIsRetryable(): void
    {
        $this->composer()->willReturn(new ComposedImage('image/svg+xml', FakeProductImageComposer::defaultImage()->bytes));

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\UnexpectedValueException $e) {
            self::assertStringContainsString('image/svg+xml', $e->getMessage());
        }

        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
        self::assertNull($placement->getResultVersion());
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->composer()->willThrow(new ImageGenerationRateLimitedException('slow down', 7000));

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry(), 'Retries must stay bounded by the transport retry strategy.');
        }

        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
    }

    public function testUnavailableProviderIsRetryable(): void
    {
        $this->composer()->willThrow(new ImageGenerationUnavailableException('boom'));

        $placement = $this->placementWithAssets();

        try {
            $this->handler()(new GeneratePlacementImage($placement->getId()->toRfc4122()));
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

    private function composer(): FakeProductImageComposer
    {
        return static::getContainer()->get(FakeProductImageComposer::class);
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
}
