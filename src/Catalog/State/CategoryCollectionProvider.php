<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\QueryParameters;
use App\Api\State\UriVariables;
use App\Catalog\ApiResource\CatalogCategory;
use App\Catalog\Query\ListCategories;

/**
 * @implements ProviderInterface<CatalogCategory>
 */
final class CategoryCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @return list<CatalogCategory>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $parentId = UriVariables::uuid(QueryParameters::value($operation, 'parent'));

        return $this->queryBus->ask(new ListCategories($parentId));
    }
}
