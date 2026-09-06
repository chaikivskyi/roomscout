<?php

namespace App\Catalog\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;

final class ProductCriteriaFilter
{
    /**
     * @param non-empty-list<string>|null $categoryIds
     */
    public static function apply(
        QueryBuilder $qb,
        string $alias,
        ?int $priceMin,
        ?int $priceMax,
        ?array $categoryIds,
    ): void {
        if (null !== $priceMin) {
            $qb->andWhere(sprintf('%s.price >= :priceMin', $alias))->setParameter('priceMin', $priceMin);
        }

        if (null !== $priceMax) {
            $qb->andWhere(sprintf('%s.price <= :priceMax', $alias))->setParameter('priceMax', $priceMax);
        }

        if (null !== $categoryIds) {
            $qb->andWhere(sprintf('%s.category IN (:categoryIds)', $alias))
                ->setParameter('categoryIds', $categoryIds, ArrayParameterType::STRING);
        }
    }
}
