<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Entity\ProjectContextEmbedding;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Project\Entity\ProjectContext;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ContextProductMatcher
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

    public function match(ProjectContext $context, ProjectContextEmbedding $embedding): int
    {
        $insertedCount = (int) $this->entityManager->wrapInTransaction(function () use ($context, $embedding): int {
            $count = $this->matches->insertMatchesWithinCosineDistance(
                $context->getId(),
                $embedding->getEmbedding(),
                1.0 - $this->minMatchScore,
                self::MAX_MATCHES,
                $embedding->getModel(),
                new \DateTimeImmutable(),
            );

            $context->markCompleted();

            return $count;
        });

        if (self::MAX_MATCHES === $insertedCount) {
            $this->logger->warning('Match cap hit; result set truncated.', [
                'contextId' => (string) $context->getId(),
                'cap' => self::MAX_MATCHES,
            ]);
        }

        $this->logger->info('Stored product matches for context.', [
            'contextId' => (string) $context->getId(),
            'count' => $insertedCount,
        ]);

        return $insertedCount;
    }
}
