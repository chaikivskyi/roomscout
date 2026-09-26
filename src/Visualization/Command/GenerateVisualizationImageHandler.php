<?php

namespace App\Visualization\Command;

use App\Project\Entity\ProjectImageVersion;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\ProjectImageStorage;
use App\Visualization\Entity\ProductVisualization;
use App\Visualization\Enum\VisualizationStatus;
use App\Visualization\Exception\ImageGenerationRateLimitedException;
use App\Visualization\Exception\ImageGenerationRejectedException;
use App\Visualization\Service\ProductImageComposerInterface;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final class GenerateVisualizationImageHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProductImageComposerInterface $composer,
        private readonly ProjectImageStorage $projectImageStorage,
        private readonly ProjectImageVersionRepository $imageVersions,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GenerateVisualizationImage $command): void
    {
        $visualization = $this->entityManager->find(ProductVisualization::class, $this->parseVisualizationId($command->visualizationId));

        if (null === $visualization) {
            $this->logger->info('Skipping generation: visualization was deleted.', ['visualizationId' => $command->visualizationId]);

            return;
        }

        if (VisualizationStatus::Processing !== $visualization->getStatus()) {
            $this->logger->debug('Skipping generation: visualization is already terminal.', [
                'visualizationId' => $command->visualizationId,
                'status' => $visualization->getStatus()->value,
            ]);

            return;
        }

        $roomVersion = $this->imageVersions->findLatestForProject($visualization->getProject()->getId());

        if (null === $roomVersion || !$this->projectImageStorage->exists($roomVersion->getImagePath())) {
            $this->failVisualization($visualization, 'project image is missing from storage');

            return;
        }

        $roomPath = $roomVersion->getImagePath();
        $product = $visualization->getProduct();
        $thumbnailPath = $product?->getThumbnailUrl();

        if (null === $product || null === $thumbnailPath || !$this->thumbnailStorage->fileExists($thumbnailPath)) {
            $this->failVisualization($visualization, 'product or its thumbnail is gone');

            return;
        }

        try {
            $image = $this->composer->compose(
                $visualization->getPrompt(),
                $this->projectImageStorage->mimeType($roomPath),
                $this->projectImageStorage->read($roomPath),
                $this->thumbnailStorage->mimeType($thumbnailPath),
                $this->thumbnailStorage->read($thumbnailPath),
            );
        } catch (ImageGenerationRateLimitedException $e) {
            throw new RecoverableMessageHandlingException($e->getMessage(), previous: $e, retryDelay: $e->getRetryDelayMs(), forceRetry: false);
        } catch (ImageGenerationRejectedException $e) {
            throw new UnrecoverableMessageHandlingException($e->getMessage(), previous: $e);
        }

        $path = $this->projectImageStorage->storeBytes($image->mimeType, $image->bytes, $visualization->getProject()->getUser()->getId());

        try {
            $version = new ProjectImageVersion($visualization->getProject(), $path);
            $this->entityManager->persist($version);
            $visualization->markCompleted($version);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            try {
                $this->projectImageStorage->remove($path);
            } catch (\Throwable $cleanupFailure) {
                $this->logger->warning('Orphaned visualization image could not be cleaned up.', [
                    'path' => $path,
                    'reason' => $cleanupFailure->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    private function failVisualization(ProductVisualization $visualization, string $reason): void
    {
        $visualization->markFailed();
        $this->entityManager->flush();
        $this->logger->warning(sprintf('Visualization failed: %s.', $reason), ['visualizationId' => (string) $visualization->getId()]);
    }

    private function parseVisualizationId(string $visualizationId): Uuid
    {
        try {
            return Uuid::fromString($visualizationId);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(sprintf('Malformed visualization id "%s".', $visualizationId), previous: $e);
        }
    }
}
