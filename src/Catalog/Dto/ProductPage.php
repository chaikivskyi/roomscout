<?php

namespace App\Catalog\Dto;

use App\Catalog\ApiResource\CatalogProduct;

final class ProductPage
{
    /**
     * @param list<CatalogProduct> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
