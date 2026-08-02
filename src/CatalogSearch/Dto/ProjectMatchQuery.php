<?php

namespace App\CatalogSearch\Dto;

use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;

final class ProjectMatchQuery
{
    /**
     * @param non-empty-list<string>|null $categoryIds
     */
    public function __construct(
        public readonly ?int $priceMin,
        public readonly ?int $priceMax,
        public readonly ?array $categoryIds,
        public readonly MatchSort $sort,
        public readonly SortDirection $direction,
    ) {
    }
}
