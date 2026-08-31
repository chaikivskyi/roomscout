<?php

namespace App\Catalog\Dto;

use App\Catalog\Exception\InvalidProductFilters;
use Symfony\Component\Uid\Uuid;

final class ProductFilters
{
    public function __construct(
        public readonly ?int $priceMin = null,
        public readonly ?int $priceMax = null,
        public readonly ?Uuid $categoryId = null,
    ) {
        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new InvalidProductFilters('min price must not exceed max price.');
        }
    }
}
