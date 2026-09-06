<?php

namespace App\Catalog\ApiResource;

use ApiPlatform\Doctrine\Orm\State\Options;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Catalog\Entity\Product;
use App\Catalog\Filter\CategorySubtreeFilter;
use App\Catalog\State\ProductCollectionProvider;
use App\Catalog\Validator\ValidPriceRange;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/catalog/products',
        paginationItemsPerPage: 15,
        security: "is_granted('PUBLIC_ACCESS')",
        openapi: new Operation(
            tags: ['Catalog / Products'],
            summary: 'List catalog products',
            description: 'The public product catalog, newest first. No authentication required.',
            security: [],
        ),
        parameters: [
            'price' => new QueryParameter(
                filter: 'app.catalog.filter.price_range',
                property: 'price',
                description: 'Price bounds, e.g. price[gte]=50&price[lte]=200. Products without a price are excluded when either bound is set.',
                constraints: [new All([new Type('numeric'), new GreaterThanOrEqual(0)]), new ValidPriceRange()],
            ),
            'category' => new QueryParameter(
                filter: CategorySubtreeFilter::class,
                schema: ['type' => 'string', 'format' => 'uuid'],
                description: 'Category id (UUID); includes products in this category and any of its descendants. An unknown category yields an empty page.',
            ),
        ],
        order: ['id' => 'DESC'],
        forceEager: false,
        stateOptions: new Options(entityClass: Product::class),
        provider: ProductCollectionProvider::class,
    ),
])]
final class CatalogProduct
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?float $price,
        public readonly string $imageUrl,
        public readonly string $url,
        #[ApiProperty(genId: false)]
        public readonly ProductCategory $category,
    ) {
    }
}
