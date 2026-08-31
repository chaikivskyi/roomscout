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
    }
}
