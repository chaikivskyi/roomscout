<?php

namespace App\Catalog\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Catalog\Entity\Category;
use App\Catalog\Filter\ParentCategoryFilter;
use App\Catalog\State\CategoryCollectionProvider;

#[ApiResource(normalizationContext: ['skip_null_values' => false], operations: [
    new GetCollection(
        uriTemplate: '/catalog/categories',
        paginationEnabled: false,
        security: "is_granted('PUBLIC_ACCESS')",
        openapi: new Operation(
            tags: ['Catalog / Categories'],
            summary: 'List catalog categories',
            description: 'The root categories by default, or the direct children of one category. Each item carries its parent id, so a child response also identifies the parent it hangs from. No authentication required.',
            security: [],
        ),
        parameters: [
            'parent' => new QueryParameter(
                filter: ParentCategoryFilter::class,
                schema: ['type' => 'string', 'format' => 'uuid'],
                description: 'Parent category id (UUID); returns that category\'s direct children instead of the root categories. An unknown category yields an empty collection.',
            ),
        ],
        order: ['title' => 'ASC'],
        stateOptions: new Options(entityClass: Category::class),
        provider: CategoryCollectionProvider::class,
    ),
])]
final class CatalogCategory
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?string $iconUrl,
        public readonly ?string $parentId,
    ) {
    }
}
