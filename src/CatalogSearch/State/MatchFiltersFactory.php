<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use App\Api\State\QueryParameters;
use App\Api\State\Uuids;
use App\CatalogSearch\Dto\MatchFilters;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;

final class MatchFiltersFactory
{
    public function create(Operation $operation): MatchFilters
    {
        $price = QueryParameters::value($operation, 'price');
        $order = QueryParameters::value($operation, 'order');
        $category = QueryParameters::value($operation, 'category');

        $lower = \is_array($price) ? ($price['gte'] ?? $price['gt'] ?? null) : null;
        $upper = \is_array($price) ? ($price['lte'] ?? $price['lt'] ?? null) : null;

        $sort = MatchSort::Score;
        $direction = SortDirection::Desc;

        if (\is_array($order) && [] !== $order) {
            $field = array_key_first($order);
            $sort = MatchSort::tryFrom((string) $field) ?? MatchSort::Score;
            $value = $order[$field];
            $direction = \is_string($value) ? SortDirection::tryFrom(strtolower($value)) ?? SortDirection::Desc : SortDirection::Desc;
        }

        return new MatchFilters(
            priceMin: is_numeric($lower) ? (float) $lower : null,
            priceMax: is_numeric($upper) ? (float) $upper : null,
            categoryId: Uuids::orNull($category),
            sort: $sort,
            direction: $direction,
        );
    }
}
