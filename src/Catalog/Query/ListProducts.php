<?php

namespace App\Catalog\Query;

use App\Api\Bus\QueryInterface;
use App\Catalog\Dto\ProductFilters;
use App\Catalog\Dto\ProductPage;

/**
 * @implements QueryInterface<ProductPage>
 */
final class ListProducts implements QueryInterface
{
    public function __construct(
        public readonly ProductFilters $filters,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
