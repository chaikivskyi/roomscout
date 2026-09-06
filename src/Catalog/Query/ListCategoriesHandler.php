<?php

namespace App\Catalog\Query;

use App\Catalog\ApiResource\CatalogCategory;
use App\Catalog\Repository\CategoryRepository;
use App\Catalog\Service\CatalogCategoryMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListCategoriesHandler
{
    public function __construct(
        private readonly CategoryRepository $categories,
        private readonly CatalogCategoryMapper $mapper,
    ) {
    }

    /**
     * @return list<CatalogCategory>
     */
    public function __invoke(ListCategories $query): array
    {
        $categories = null === $query->parentId
            ? $this->categories->findRoots()
            : $this->categories->findChildrenOf($query->parentId);

        return array_map($this->mapper->map(...), $categories);
    }
}
