<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Catalog\ApiResource\CatalogProduct;
use App\Catalog\Query\ListProducts;

/**
 * @implements ProviderInterface<CatalogProduct>
 */
final class ProductCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ProductFiltersParser $filtersParser,
        private readonly QueryBusInterface $queryBus,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var array{int, int, int} $pagination */
        $pagination = $this->pagination->getPagination($operation, $context);
        [$page, , $limit] = $pagination;

        $filters = $this->filtersParser->parse($operation);

        $result = $this->queryBus->ask(new ListProducts(filters: $filters, page: $page, limit: $limit));

        return new TraversablePaginator(
            new \ArrayIterator($result->items),
            $result->page,
            $result->limit,
            $result->total,
        );
    }
}
