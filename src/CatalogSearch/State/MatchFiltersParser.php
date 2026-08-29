<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use App\CatalogSearch\Dto\MatchFilters;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;
use Symfony\Component\Uid\Uuid;

final class MatchFiltersParser
{
    public function parse(Operation $operation): MatchFilters
    {
        $priceMin = $this->parameter($operation, 'priceMin');
        $priceMax = $this->parameter($operation, 'priceMax');
        $categoryId = $this->parameter($operation, 'category');
        $sort = $this->parameter($operation, 'sort');
        $direction = $this->parameter($operation, 'direction');

        return new MatchFilters(
            priceMin: \is_int($priceMin) ? $priceMin : null,
            priceMax: \is_int($priceMax) ? $priceMax : null,
            categoryId: \is_string($categoryId) && Uuid::isValid($categoryId) ? Uuid::fromString($categoryId) : null,
            sort: \is_string($sort) ? MatchSort::from($sort) : MatchSort::Score,
            direction: \is_string($direction) ? SortDirection::from($direction) : SortDirection::Desc,
        );
    }

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }
}
