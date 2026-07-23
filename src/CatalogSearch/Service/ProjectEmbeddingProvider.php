<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Entity\ProjectEmbedding;
use App\CatalogSearch\Repository\ProjectEmbeddingRepository;
use App\Project\Entity\Project;
use App\Project\Service\ProjectImageStorage;
use Pgvector\Vector;
use Psr\Log\LoggerInterface;

final class ProjectEmbeddingProvider
{
    public function __construct(
        private readonly ProjectEmbeddingRepository $embeddings,
        private readonly ImageEmbedderInterface $embedder,
        private readonly ProjectImageStorage $projectImages,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function provide(Project $project): ?ProjectEmbedding
    {
        $existing = $this->embeddings->findForProject($project->getId());

        if (null !== $existing) {
            return $existing;
        }

        $path = $project->getImagePath();

        if (!$this->projectImages->exists($path)) {
            $this->logger->warning('Cannot embed project: image is missing from storage.', [
                'projectId' => (string) $project->getId(),
                'path' => $path,
            ]);

            return null;
        }

        $vector = $this->embedder->embedQuery(
            $project->getPrompt(),
            $this->projectImages->mimeType($path),
            $this->projectImages->read($path),
        );

        $embedding = new ProjectEmbedding(
            project: $project,
            embedding: new Vector($vector),
            model: $this->embedder->model(),
            embeddedAt: new \DateTimeImmutable(),
        );

        $this->embeddings->save($embedding);

        return $embedding;
    }
}
