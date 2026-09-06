<?php

namespace App\Catalog\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\State\Uuids;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bridge\Doctrine\Types\UuidType;

final class ParentCategoryFilter extends AbstractFilter
{
    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $parent = Uuids::orNull($value);

        if (null === $parent) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('parentCategory');

        $queryBuilder
            ->andWhere(\sprintf('%s.parent = :%s', $alias, $parameter))
            ->setParameter($parameter, $parent, UuidType::NAME);
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
