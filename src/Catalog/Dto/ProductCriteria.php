<?php

namespace App\Catalog\Dto;

final class ProductCriteria
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
    ) {
        ProductCriteriaRules::assert($page, $limit, $priceMin, $priceMax);
    }
}
