<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Repository\ProjectContextEmbeddingRepository;
use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectImageVersionRepository;
use App\Project\Service\ProjectImageStorage;
use Pgvector\Vector;
use Psr\Log\LoggerInterface;

final class ContextEmbeddingProvider
{
    public function __construct(
        private readonly ProjectContextEmbeddingRepository $embeddings,
        private readonly ImageEmbedderInterface $embedder,
        private readonly ProjectImageStorage $projectImages,
        private readonly ProjectImageVersionRepository $imageVersions,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function provide(ProjectContext $context): ?ProjectContextEmbedding
    {
        $existing = $this->embeddings->findForContext($context->getId());

        if (null !== $existing) {
            return $existing;
        }

        $version = $this->imageVersions->findLatestForProject($context->getProject()->getId());

        if (null === $version) {
            $this->logger->warning('Cannot embed context: project has no image version.', [
                'contextId' => (string) $context->getId(),
            ]);

            return null;
        }

        $path = $version->getImagePath();

        if (!$this->projectImages->exists($path)) {
            $this->logger->warning('Cannot embed context: project image is missing from storage.', [
                'contextId' => (string) $context->getId(),
                'path' => $path,
            ]);

            return null;
        }

        $vector = $this->embedder->embedQuery(
            $context->getPrompt(),
            $this->projectImages->mimeType($path),
            $this->projectImages->read($path),
        );

        $embedding = new ProjectContextEmbedding(
            context: $context,
            embedding: new Vector($vector),
            model: $this->embedder->model(),
            embeddedAt: new \DateTimeImmutable(),
        );

        $this->embeddings->save($embedding);

        return $embedding;
    }
}
