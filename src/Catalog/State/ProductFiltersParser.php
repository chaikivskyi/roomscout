<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use App\Api\State\QueryParameters;
use App\Api\State\UriVariables;
use App\Catalog\Dto\ProductFilters;

final class ProductFiltersParser
{
    public function parse(Operation $operation): ProductFilters
    {
        $priceMin = QueryParameters::value($operation, 'priceMin');
        $priceMax = QueryParameters::value($operation, 'priceMax');
        $categoryId = QueryParameters::value($operation, 'category');

        return new ProductFilters(
            priceMin: \is_int($priceMin) ? $priceMin : null,
            priceMax: \is_int($priceMax) ? $priceMax : null,
            categoryId: UriVariables::uuid($categoryId),
        );
    }
}
