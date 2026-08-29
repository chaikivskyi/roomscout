<?php

namespace App\Placement\Command;

use App\Placement\Entity\ProductPlacement;
use App\Placement\Enum\PlacementStatus;
use App\Placement\Exception\ImageGenerationRateLimitedException;
use App\Placement\Exception\ImageGenerationRejectedException;
use App\Placement\Service\ProductImageComposerInterface;
use App\Project\Entity\ProjectImageVersion;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\ProjectImageStorage;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\FilesystemOperator;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final class GeneratePlacementImageHandler
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

    public function __invoke(GeneratePlacementImage $command): void
    {
        $placement = $this->entityManager->find(ProductPlacement::class, $this->parsePlacementId($command->placementId));

        if (null === $placement) {
            $this->logger->info('Skipping generation: placement was deleted.', ['placementId' => $command->placementId]);

            return;
        }

        if (PlacementStatus::Processing !== $placement->getStatus()) {
            $this->logger->debug('Skipping generation: placement is already terminal.', [
                'placementId' => $command->placementId,
                'status' => $placement->getStatus()->value,
            ]);

            return;
        }

        $roomVersion = $this->imageVersions->findLatestForProject($placement->getProject()->getId());

        if (null === $roomVersion || !$this->projectImageStorage->exists($roomVersion->getImagePath())) {
            $this->failPlacement($placement, 'project image is missing from storage');

            return;
        }

        $roomPath = $roomVersion->getImagePath();
        $product = $placement->getProduct();
        $thumbnailPath = $product?->getThumbnailUrl();

        if (null === $product || null === $thumbnailPath || !$this->thumbnailStorage->fileExists($thumbnailPath)) {
            $this->failPlacement($placement, 'product or its thumbnail is gone');

            return;
        }

        try {
            $image = $this->composer->compose(
                $placement->getPrompt(),
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

        $path = $this->projectImageStorage->storeBytes($image->mimeType, $image->bytes);

        try {
            $version = new ProjectImageVersion($placement->getProject(), $path);
            $this->entityManager->persist($version);
            $placement->markCompleted($version);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            try {
                $this->projectImageStorage->remove($path);
            } catch (\Throwable $cleanupFailure) {
                $this->logger->warning('Orphaned placement image could not be cleaned up.', [
                    'path' => $path,
                    'reason' => $cleanupFailure->getMessage(),
                ]);
            }

            throw $e;
        }
    }

    private function failPlacement(ProductPlacement $placement, string $reason): void
    {
        $placement->markFailed();
        $this->entityManager->flush();
        $this->logger->warning(sprintf('Placement failed: %s.', $reason), ['placementId' => (string) $placement->getId()]);
    }

    private function parsePlacementId(string $placementId): Uuid
    {
        try {
            return Uuid::fromString($placementId);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(sprintf('Malformed placement id "%s".', $placementId), previous: $e);
        }
    }
}
