<?php

namespace App\Catalog\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Catalog\Entity\Product;
use Doctrine\ORM\QueryBuilder;

final class CategoryJoinExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Product::class !== $resourceClass) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $join = $queryNameGenerator->generateJoinAlias('category');

        $queryBuilder->addSelect($join)->leftJoin(\sprintf('%s.category', $alias), $join);
    }
}
