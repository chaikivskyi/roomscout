<?php

namespace App\Catalog\ApiResource;

final class ProductCategory
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
    ) {
    }
}
