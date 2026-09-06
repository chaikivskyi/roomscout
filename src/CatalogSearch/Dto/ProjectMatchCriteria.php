<?php

namespace App\CatalogSearch\Dto;

use App\Catalog\Dto\ProductCriteriaRules;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;

final class ProjectMatchCriteria
{
    /**
     * @param non-empty-list<string>|null $categoryIds
     */
    public function __construct(
        public readonly int $page,
        public readonly int $limit,
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly ?array $categoryIds = null,
        public readonly MatchSort $sort = MatchSort::Score,
        public readonly SortDirection $direction = SortDirection::Desc,
    ) {
        ProductCriteriaRules::assert($page, $limit, $priceMin, $priceMax);
    }
}
