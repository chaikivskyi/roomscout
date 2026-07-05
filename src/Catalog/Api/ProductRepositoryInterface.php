<?php

namespace App\Catalog\Api;

interface ProductRepositoryInterface
{
    public function create(): ProductInterface;

    public function findOneByExternalId(string $externalId): ?ProductInterface;

    public function save(ProductInterface $product): void;
}
