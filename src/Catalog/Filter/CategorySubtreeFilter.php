<?php

namespace App\Catalog\Filter;

use ApiPlatform\Doctrine\Orm\Filter\AbstractFilter;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Api\State\Uuids;
use App\Catalog\Repository\CategoryRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

final class CategorySubtreeFilter extends AbstractFilter
{
    public function __construct(
        ManagerRegistry $managerRegistry,
        private readonly CategoryRepository $categories,
        private readonly string $relation = 'category',
    ) {
        parent::__construct($managerRegistry);
    }

    protected function filterProperty(string $property, mixed $value, QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        $category = Uuids::orNull($value);

        if (null === $category) {
            return;
        }

        $ids = $this->categories->findSubtreeIds($category);

        if ([] === $ids) {
            $queryBuilder->andWhere('1 = 0');

            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $parameter = $queryNameGenerator->generateParameterName('categorySubtree');

        $queryBuilder
            ->andWhere(\sprintf('%s.%s IN (:%s)', $alias, $this->relation, $parameter))
            ->setParameter($parameter, $ids, ArrayParameterType::STRING);
    }

    public function getDescription(string $resourceClass): array
    {
        return [];
    }
}
