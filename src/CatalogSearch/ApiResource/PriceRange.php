<?php

namespace App\CatalogSearch\ApiResource;

final class PriceRange
{
    public function __construct(
        public readonly int $min,
        public readonly int $max,
    ) {
    }
}
