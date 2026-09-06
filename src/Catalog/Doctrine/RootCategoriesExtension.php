<?php

namespace App\Catalog\Doctrine;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\State\QueryParameters;
use App\Api\State\Uuids;
use App\Catalog\Entity\Category;
use Doctrine\ORM\QueryBuilder;

final class RootCategoriesExtension implements QueryCollectionExtensionInterface
{
    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Category::class !== $resourceClass) {
            return;
        }

        $parent = null !== $operation ? Uuids::orNull(QueryParameters::value($operation, 'parent')) : null;

        if (null !== $parent) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];

        $queryBuilder->andWhere(\sprintf('%s.parent IS NULL', $alias));
    }
}
