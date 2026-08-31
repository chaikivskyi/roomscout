<?php

namespace App\Catalog\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use App\Api\State\UriVariables;
use App\Catalog\Dto\ProductFilters;

final class ProductFiltersParser
{
    public function parse(Operation $operation): ProductFilters
    {
        $priceMin = $this->parameter($operation, 'priceMin');
        $priceMax = $this->parameter($operation, 'priceMax');
        $categoryId = $this->parameter($operation, 'category');

        return new ProductFilters(
            priceMin: \is_int($priceMin) ? $priceMin : null,
            priceMax: \is_int($priceMax) ? $priceMax : null,
            categoryId: UriVariables::uuid($categoryId),
        );
    }

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }
}
