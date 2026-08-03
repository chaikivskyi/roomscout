<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Enum\MatchSort;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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
            $qb->andWhere('p.category IN (:categoryIds)')
                ->setParameter('categoryIds', $criteria->categoryIds, ArrayParameterType::STRING);
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

    /**
     * @return list<array{id: string, title: string, count: int}>
     */
    public function countByCategoryForContext(Uuid $contextId, ?int $priceMin, ?int $priceMax): array
    {
        $priced = [];

        if (null !== $priceMin) {
            $priced[] = 'p.price >= :priceMin';
        }

        if (null !== $priceMax) {
            $priced[] = 'p.price <= :priceMax';
        }

        $countExpr = [] === $priced
            ? 'COUNT(m)'
            : sprintf('SUM(CASE WHEN %s THEN 1 ELSE 0 END)', implode(' AND ', $priced));

        $qb = $this->createQueryBuilder('m')
            ->select('c.id AS id', 'c.title AS title', $countExpr.' AS matchCount')
            ->join('m.product', 'p')
            ->join('p.category', 'c')
            ->where('m.context = :context')
            ->setParameter('context', $contextId, UuidType::NAME)
            ->groupBy('c.id', 'c.title')
            ->orderBy('matchCount', 'DESC')
            ->addOrderBy('c.title', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (null !== $priceMin) {
            $qb->setParameter('priceMin', $priceMin);
        }

        if (null !== $priceMax) {
            $qb->setParameter('priceMax', $priceMax);
        }

        /** @var list<array{id: Uuid|string, title: string, matchCount: int|numeric-string}> $rows */
        $rows = $qb->getQuery()->getResult();

        return array_map(static fn (array $row) => [
            'id' => (string) $row['id'],
            'title' => $row['title'],
            'count' => (int) $row['matchCount'],
        ], $rows);
    }

    /**
     * @param list<string>|null $categoryIds
     *
     * @return array{min: float, max: float}|null
     */
    public function findPriceRangeForContext(Uuid $contextId, ?array $categoryIds): ?array
    {
        $qb = $this->createQueryBuilder('m')
            ->select('MIN(p.price) AS minPrice', 'MAX(p.price) AS maxPrice')
            ->join('m.product', 'p')
            ->where('m.context = :context')
            ->andWhere('p.price IS NOT NULL')
            ->setParameter('context', $contextId, UuidType::NAME);

        if (null !== $categoryIds) {
            $qb->andWhere('p.category IN (:categoryIds)')
                ->setParameter('categoryIds', $categoryIds, ArrayParameterType::STRING);
        }

        /** @var array{minPrice: float|numeric-string|null, maxPrice: float|numeric-string|null} $row */
        $row = $qb->getQuery()->getSingleResult();

        if (null === $row['minPrice'] || null === $row['maxPrice']) {
            return null;
        }

        return ['min' => (float) $row['minPrice'], 'max' => (float) $row['maxPrice']];
    }

    public function existsForContext(Uuid $contextId): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM project_product_match WHERE context_id = :contextId)',
            ['contextId' => (string) $contextId],
        );
    }

    public function existsForContextAndProduct(Uuid $contextId, Uuid $productId): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM project_product_match WHERE context_id = :contextId AND product_id = :productId)',
            ['contextId' => $contextId->toRfc4122(), 'productId' => (string) $productId],
        );
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
                INSERT INTO project_product_match (id, context_id, product_id, match_score, model, matched_at)
                SELECT uuidv7(), :contextId, e.product_id, 1 - (e.embedding <=> :query), :model, :matchedAt
                FROM product_embedding e
                WHERE (e.embedding <=> :query) <= :maxDistance
                ORDER BY e.embedding <=> :query
                LIMIT :limit
                SQL,
            [
                'contextId' => (string) $contextId,
                'query' => (string) $query,
                'maxDistance' => $maxDistance,
                'limit' => $limit,
                'model' => $model,
                'matchedAt' => $matchedAt->format('Y-m-d H:i:s'),
            ],
        );
    }
}
