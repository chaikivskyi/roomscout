<?php

namespace App\Catalog\Service;

use App\Catalog\ApiResource\CatalogCategory;
use App\Catalog\Entity\Category;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class CatalogCategoryMapper
{
    public function __construct(
        #[Autowire(service: 'category_icons.storage')]
        private readonly FilesystemOperator $iconStorage,
    ) {
    }

    public function map(Category $category): CatalogCategory
    {
        $iconUrl = $category->getIconUrl();
        $parent = $category->getParent();

        return new CatalogCategory(
            id: (string) $category->getId(),
            title: (string) $category->getTitle(),
            iconUrl: null === $iconUrl ? null : $this->iconStorage->publicUrl($iconUrl),
            parentId: null === $parent ? null : (string) $parent->getId(),
        );
    }
}
