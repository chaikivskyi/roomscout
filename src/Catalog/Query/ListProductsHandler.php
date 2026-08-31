<?php

namespace App\Catalog\Query;

use App\Catalog\Dto\ProductCriteria;
use App\Catalog\Dto\ProductPage;
use App\Catalog\Repository\ProductRepository;
use App\Catalog\Service\CatalogProductMapper;
use App\Catalog\Service\CategorySubtreeResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProductsHandler
{
    public function __construct(
        private readonly CategorySubtreeResolver $subtree,
        private readonly ProductRepository $products,
        private readonly CatalogProductMapper $mapper,
    ) {
    }

    public function __invoke(ListProducts $query): ProductPage
    {
        $categoryIds = $this->subtree->resolve($query->filters->categoryId);

        if (null !== $query->filters->categoryId && null === $categoryIds) {
            return new ProductPage([], 0, $query->page, $query->limit);
        }

        ['items' => $items, 'total' => $total] = $this->products->findPage(
            new ProductCriteria(
                page: $query->page,
                limit: $query->limit,
                priceMin: $query->filters->priceMin,
                priceMax: $query->filters->priceMax,
                categoryIds: $categoryIds,
            ),
        );

        return new ProductPage(
            array_map($this->mapper->map(...), $items),
            $total,
            $query->page,
            $query->limit,
        );
    }
}
