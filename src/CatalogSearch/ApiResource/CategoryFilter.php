<?php

namespace App\CatalogSearch\ApiResource;

final class CategoryFilter
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly int $count,
    ) {
    }
}
