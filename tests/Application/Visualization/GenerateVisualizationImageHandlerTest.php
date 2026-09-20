<?php

namespace App\Tests\Application\Visualization;

use App\Visualization\Command\GenerateVisualizationImage;
use App\Visualization\Command\GenerateVisualizationImageHandler;
use App\Visualization\Dto\ComposedImage;
use App\Visualization\Entity\ProductVisualization;
use App\Visualization\Enum\VisualizationStatus;
use App\Visualization\Exception\ImageGenerationRateLimitedException;
use App\Visualization\Exception\ImageGenerationRejectedException;
use App\Visualization\Exception\ImageGenerationUnavailableException;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use App\Tests\Factory\ProductVisualizationFactory;
use App\Tests\Factory\ProjectContextFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Fake\FakeProductImageComposer;
use Doctrine\ORM\Events;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

final class GenerateVisualizationImageHandlerTest extends ApiTestCase
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

        $visualization = $this->visualizationWithAssets('put the table under the window');

        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));

        self::assertSame(VisualizationStatus::Completed, $visualization->getStatus());

        $resultVersion = $visualization->getResultVersion();
        self::assertNotNull($resultVersion);
        $latest = static::getContainer()->get(ProjectImageVersionRepository::class)
            ->findLatestForProject($visualization->getProject()->getId());
        self::assertNotNull($latest);
        self::assertTrue($resultVersion->getId()->equals($latest->getId()));
        self::assertStringEndsWith('/image.png', $resultVersion->getImagePath());
        self::assertSame(FakeProductImageComposer::defaultImage()->bytes, $this->projectStorage()->read($resultVersion->getImagePath()));
        self::assertGreaterThan(
            $visualization->getCreatedAt(),
            $visualization->getUpdatedAt(),
            'PreUpdate must advance updatedAt on the processing → completed transition.',
        );

        $calls = $composer->calls();
        self::assertCount(1, $calls);
        self::assertSame('put the table under the window', $calls[0]['prompt'], 'The handler passes the prompt copied onto the visualization row.');
        self::assertSame('image/png', $calls[0]['roomMimeType']);
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['roomBytes']);
        self::assertSame('image/png', $calls[0]['productMimeType']);
        self::assertSame(base64_decode(self::PNG_1X1), $calls[0]['productBytes']);
    }

    public function testGeneratedFileIsRemovedWhenTheFlushFails(): void
    {
        $visualization = $this->visualizationWithAssets();
        $filesBefore = $this->projectStorageFiles();

        $this->entityManager()->getEventManager()->addEventListener(Events::onFlush, new class {
            public function onFlush(): void
            {
                throw new \RuntimeException('flush boom');
            }
        });

        try {
            $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
            self::fail('Expected the flush failure to bubble.');
        } catch (\RuntimeException $e) {
            self::assertSame('flush boom', $e->getMessage(), 'The original flush error must not be masked by cleanup.');
        }

        self::assertSame($filesBefore, $this->projectStorageFiles(), 'The generated file must not be orphaned when the flush fails.');
    }

    public function testTerminalVisualizationIsSkipped(): void
    {
        $composer = $this->composer();

        $visualization = ProductVisualizationFactory::new()->completed()->create();

        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(VisualizationStatus::Completed, $visualization->getStatus());
    }

    public function testDeletedVisualizationIsSkippedWithoutRetry(): void
    {
        $composer = $this->composer();

        $this->handler()(new GenerateVisualizationImage(Uuid::v7()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
    }

    public function testMalformedVisualizationIdIsNotRetried(): void
    {
        $composer = $this->composer();

        try {
            $this->handler()(new GenerateVisualizationImage('999999'));
            self::fail('Expected an unrecoverable exception.');
        } catch (UnrecoverableMessageHandlingException $e) {
            self::assertStringContainsString('999999', $e->getMessage());
        }

        self::assertSame(0, $composer->callCount());
    }

    public function testProjectWithoutAnyImageVersionFailsTheVisualization(): void
    {
        $composer = $this->composer();

        $visualization = ProductVisualizationFactory::createOne();

        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(VisualizationStatus::Failed, $visualization->getStatus());
    }

    public function testMissingProjectImageFileFailsTheVisualization(): void
    {
        $composer = $this->composer();

        $visualization = ProductVisualizationFactory::createOne();
        ProjectImageVersionFactory::createOne(['project' => $visualization->getProject()]);

        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(VisualizationStatus::Failed, $visualization->getStatus());
    }

    public function testMissingProductThumbnailFailsTheVisualization(): void
    {
        $composer = $this->composer();

        $visualization = ProductVisualizationFactory::createOne([
            'product' => ProductFactory::new(['thumbnailUrl' => 'gone/thumbnail.png']),
        ]);
        ProjectImageVersionFactory::createOne(['project' => $visualization->getProject(), 'imagePath' => 'room-nothumb/image.png']);
        $this->projectStorage()->write('room-nothumb/image.png', base64_decode(self::PNG_1X1));

        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        self::assertSame(VisualizationStatus::Failed, $visualization->getStatus());
    }

    public function testDeletedProductFailsTheVisualization(): void
    {
        $composer = $this->composer();

        $visualization = $this->visualizationWithAssets();
        $visualizationId = $visualization->getId();

        $entityManager = $this->entityManager();
        $product = $visualization->getProduct();
        self::assertNotNull($product);
        $entityManager->remove($product);
        $entityManager->flush();
        $entityManager->clear();

        $this->handler()(new GenerateVisualizationImage($visualizationId->toRfc4122()));

        self::assertSame(0, $composer->callCount());
        $reloaded = $entityManager->find(ProductVisualization::class, $visualizationId);
        self::assertNotNull($reloaded);
        self::assertSame(VisualizationStatus::Failed, $reloaded->getStatus());
        self::assertNull($reloaded->getProduct());
    }

    public function testRejectedGenerationIsUnrecoverable(): void
    {
        $this->composer()->willThrow(new ImageGenerationRejectedException('invalid request'));

        $visualization = $this->visualizationWithAssets();

        $this->expectException(UnrecoverableMessageHandlingException::class);
        $this->expectExceptionMessage('invalid request');
        $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
    }

    public function testUnmappedImageMimeTypeIsNeverWrittenToStorage(): void
    {
        $this->composer()->willReturn(new ComposedImage('image/php', FakeProductImageComposer::defaultImage()->bytes));

        $visualization = $this->visualizationWithAssets();
        $filesBefore = $this->projectStorageFiles();

        try {
            $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
            self::fail('Expected an unsupported mime type to be rejected.');
        } catch (\UnexpectedValueException $e) {
            self::assertStringContainsString('image/php', $e->getMessage());
        }

        self::assertSame($filesBefore, $this->projectStorageFiles(), 'A response-controlled extension must never reach the web-served directory.');
        self::assertSame(VisualizationStatus::Processing, $visualization->getStatus());
        self::assertNull($visualization->getResultVersion());
    }

    public function testNonImageResultMimeTypeIsRetryable(): void
    {
        $this->composer()->willReturn(new ComposedImage('image/svg+xml', FakeProductImageComposer::defaultImage()->bytes));

        $visualization = $this->visualizationWithAssets();

        try {
            $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\UnexpectedValueException $e) {
            self::assertStringContainsString('image/svg+xml', $e->getMessage());
        }

        self::assertSame(VisualizationStatus::Processing, $visualization->getStatus());
        self::assertNull($visualization->getResultVersion());
    }

    public function testRateLimitHonorsRetryAfter(): void
    {
        $this->composer()->willThrow(new ImageGenerationRateLimitedException('slow down', 7000));

        $visualization = $this->visualizationWithAssets();

        try {
            $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
            self::fail('Expected a recoverable exception.');
        } catch (RecoverableMessageHandlingException $e) {
            self::assertSame(7000, $e->getRetryDelay());
            self::assertFalse($e->forceRetry(), 'Retries must stay bounded by the transport retry strategy.');
        }

        self::assertSame(VisualizationStatus::Processing, $visualization->getStatus());
    }

    public function testUnavailableProviderIsRetryable(): void
    {
        $this->composer()->willThrow(new ImageGenerationUnavailableException('boom'));

        $visualization = $this->visualizationWithAssets();

        try {
            $this->handler()(new GenerateVisualizationImage($visualization->getId()->toRfc4122()));
            self::fail('Expected a retryable exception.');
        } catch (\RuntimeException $e) {
            self::assertNotInstanceOf(UnrecoverableMessageHandlingException::class, $e);
            self::assertStringContainsString('boom', $e->getMessage());
        }
    }

    private function visualizationWithAssets(?string $prompt = null): ProductVisualization
    {
        $roomPath = Uuid::v7()->toRfc4122().'/image.png';
        $thumbnailPath = Uuid::v7()->toRfc4122().'/thumbnail.png';

        $contextAttributes = [];

        if (null !== $prompt) {
            $contextAttributes['prompt'] = $prompt;
        }

        $visualization = ProductVisualizationFactory::createOne([
            'context' => ProjectContextFactory::new($contextAttributes)->completed(),
            'product' => ProductFactory::new(['thumbnailUrl' => $thumbnailPath]),
        ]);
        ProjectImageVersionFactory::createOne(['project' => $visualization->getProject(), 'imagePath' => $roomPath]);

        $this->projectStorage()->write($roomPath, base64_decode(self::PNG_1X1));
        $this->thumbnailStorage()->write($thumbnailPath, base64_decode(self::PNG_1X1));

        return $visualization;
    }

    private function composer(): FakeProductImageComposer
    {
        return static::getContainer()->get(FakeProductImageComposer::class);
    }

    private function handler(): GenerateVisualizationImageHandler
    {
        return static::getContainer()->get(GenerateVisualizationImageHandler::class);
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
