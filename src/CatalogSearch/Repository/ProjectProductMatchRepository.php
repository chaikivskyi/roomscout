<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Entity\ProjectProductMatch;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectProductMatch>
 */
class ProjectProductMatchRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectProductMatch::class);
    }

    public function existsForProject(Uuid $projectId): bool
    {
        return 0 < $this->count(['project' => $projectId]);
    }

    /**
     * @return int number of matches inserted
     */
    public function insertMatchesWithinCosineDistance(
        Uuid $projectId,
        Vector $query,
        float $maxDistance,
        int $limit,
        string $model,
        \DateTimeImmutable $matchedAt,
    ): int {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO project_product_match (project_id, product_id, match_score, model, matched_at)
                SELECT :projectId, e.product_id, 1 - (e.embedding <=> :query), :model, :matchedAt
                FROM product_embedding e
                WHERE (e.embedding <=> :query) <= :maxDistance
                ORDER BY e.embedding <=> :query
                LIMIT :limit
                SQL,
            [
                'projectId' => $projectId->toRfc4122(),
                'query' => (string) $query,
                'maxDistance' => $maxDistance,
                'limit' => $limit,
                'model' => $model,
                'matchedAt' => $matchedAt->format('Y-m-d H:i:s'),
            ],
        );
    }
}
