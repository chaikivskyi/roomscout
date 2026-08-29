<?php

namespace App\CatalogSearch\Dto;

use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;
use App\CatalogSearch\Exception\InvalidMatchFilters;
use Symfony\Component\Uid\Uuid;

final class MatchFilters
{
    public function __construct(
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly ?Uuid $categoryId = null,
        public readonly MatchSort $sort = MatchSort::Score,
        public readonly SortDirection $direction = SortDirection::Desc,
    ) {
        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new InvalidMatchFilters('min price must not exceed max price.');
        }
    }
}
