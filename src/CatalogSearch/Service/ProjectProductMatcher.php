<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Entity\ProjectEmbedding;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Project\Entity\Project;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProjectProductMatcher
{
    private const MAX_MATCHES = 1000;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectProductMatchRepository $matches,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(float:CATALOG_SEARCH_MIN_MATCH_SCORE)%')]
        private readonly float $minMatchScore,
    ) {
    }

    public function match(Project $project, ProjectEmbedding $embedding): int
    {
        $insertedCount = (int) $this->entityManager->wrapInTransaction(function () use ($project, $embedding): int {
            $count = $this->matches->insertMatchesWithinCosineDistance(
                $project->getId(),
                $embedding->getEmbedding(),
                1.0 - $this->minMatchScore,
                self::MAX_MATCHES,
                $embedding->getModel(),
                new \DateTimeImmutable(),
            );

            $project->markCompleted();

            return $count;
        });

        if (self::MAX_MATCHES === $insertedCount) {
            $this->logger->warning('Match cap hit; result set truncated.', [
                'projectId' => (string) $project->getId(),
                'cap' => self::MAX_MATCHES,
            ]);
        }

        $this->logger->info('Stored product matches for project.', [
            'projectId' => (string) $project->getId(),
            'count' => $insertedCount,
        ]);

        return $insertedCount;
    }
}
