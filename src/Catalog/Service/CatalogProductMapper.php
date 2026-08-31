<?php

namespace App\Catalog\Service;

use App\Catalog\ApiResource\CatalogProduct;
use App\Catalog\ApiResource\ProductCategory;
use App\Catalog\Entity\Product;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CatalogProductMapper
{
    public function __construct(
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function map(Product $product): CatalogProduct
    {
        $category = $product->getCategory();

        return new CatalogProduct(
            id: (string) $product->getId(),
            title: (string) $product->getTitle(),
            price: $product->getPrice(),
            imageUrl: $this->thumbnailStorage->publicUrl((string) $product->getThumbnailUrl()),
            url: (string) $product->getUrl(),
            category: new ProductCategory(
                id: (string) $category?->getId(),
                title: (string) $category?->getTitle(),
            ),
        );
    }
}
