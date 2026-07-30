<?php

namespace App\CatalogSearch\Dto;

use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;

final class ProjectMatchCriteria
{
    /**
     * @param list<string>|null $categoryIds
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
        if ($page < 1) {
            throw new \InvalidArgumentException(sprintf('Page must be at least 1, got %d.', $page));
        }

        if ($limit < 1) {
            throw new \InvalidArgumentException(sprintf('Limit must be at least 1, got %d.', $limit));
        }

        if ($priceMin < 0 || $priceMax < 0) {
            throw new \InvalidArgumentException('Price bounds must not be negative.');
        }

        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new \InvalidArgumentException('Minimum price must not exceed maximum price.');
        }

        if ([] === $categoryIds) {
            throw new \InvalidArgumentException('Category ids must be null or a non-empty list.');
        }
    }
}
