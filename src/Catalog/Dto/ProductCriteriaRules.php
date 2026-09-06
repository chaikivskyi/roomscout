<?php

namespace App\Catalog\Dto;

final class ProductCriteriaRules
{
    public static function assert(int $page, int $limit, ?int $priceMin, ?int $priceMax): void
    {
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
