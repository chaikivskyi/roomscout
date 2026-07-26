<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Enum\MatchSort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;
use Pgvector\Vector;
use Symfony\Bridge\Doctrine\Types\UuidType;
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

    /**
     * @return array{items: list<ProjectProductMatch>, total: int}
     */
    public function findPageForContext(Uuid $contextId, ProjectMatchCriteria $criteria): array
    {
        $order = $criteria->direction->toOrderKeyword();

        $qb = $this->createQueryBuilder('m')
            ->addSelect('p', 'c')
            ->join('m.product', 'p')
            ->join('p.category', 'c')
            ->where('m.context = :context')
            ->setParameter('context', $contextId, UuidType::NAME);

        if (null !== $criteria->priceMin) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $criteria->priceMin);
        }

        if (null !== $criteria->priceMax) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $criteria->priceMax);
        }

        if (null !== $criteria->categoryIds) {
            $qb->andWhere('p.category IN (:categoryIds)')->setParameter('categoryIds', $criteria->categoryIds);
        }

        if (MatchSort::Price === $criteria->sort) {
            $qb->addSelect('CASE WHEN p.price IS NULL THEN 1 ELSE 0 END AS HIDDEN priceIsNull')
                ->orderBy('priceIsNull', 'ASC')
                ->addOrderBy('p.price', $order);
        } else {
            $qb->orderBy('m.matchScore', $order);
        }

        $qb->addOrderBy('p.id', 'ASC')
            ->setFirstResult(($criteria->page - 1) * $criteria->limit)
            ->setMaxResults($criteria->limit);

        /** @var Paginator<ProjectProductMatch> $paginator */
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        return [
            'items' => array_values(iterator_to_array($paginator)),
            'total' => \count($paginator),
        ];
    }

    public function existsForContext(Uuid $contextId): bool
    {
        return 0 < $this->count(['context' => $contextId]);
    }

    public function insertMatchesWithinCosineDistance(
        Uuid $contextId,
        Vector $query,
        float $maxDistance,
        int $limit,
        string $model,
        \DateTimeImmutable $matchedAt,
    ): int {
        return (int) $this->getEntityManager()->getConnection()->executeStatement(
            <<<'SQL'
                INSERT INTO project_product_match (context_id, product_id, match_score, model, matched_at)
                SELECT :contextId, e.product_id, 1 - (e.embedding <=> :query), :model, :matchedAt
                FROM product_embedding e
                WHERE (e.embedding <=> :query) <= :maxDistance
                ORDER BY e.embedding <=> :query
                LIMIT :limit
                SQL,
            [
                'contextId' => $contextId->toRfc4122(),
                'query' => (string) $query,
                'maxDistance' => $maxDistance,
                'limit' => $limit,
                'model' => $model,
                'matchedAt' => $matchedAt->format('Y-m-d H:i:s'),
            ],
        );
    }
}
