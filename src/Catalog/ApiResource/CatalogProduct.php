<?php

namespace App\Catalog\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\Catalog\State\ProductCollectionProvider;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/catalog/products',
        paginationItemsPerPage: 15,
        openapi: new Operation(
            tags: ['Catalog / Products'],
            summary: 'List catalog products',
            description: 'The public product catalog, newest first. No authentication required.',
            security: [],
        ),
        parameters: [
            'priceMin' => new QueryParameter(
                schema: ['type' => 'integer', 'minimum' => 0],
                description: 'Lowest product price to include (whole units); products without a price are excluded when set.',
                constraints: [new Type('integer'), new GreaterThanOrEqual(0)],
                castToNativeType: true,
            ),
            'priceMax' => new QueryParameter(
                schema: ['type' => 'integer', 'minimum' => 0],
                description: 'Highest product price to include (whole units); products without a price are excluded when set.',
                constraints: [new Type('integer'), new GreaterThanOrEqual(0)],
                castToNativeType: true,
            ),
            'category' => new QueryParameter(
                schema: ['type' => 'string', 'format' => 'uuid'],
                description: 'Category id (UUID); includes products in this category and any of its descendants. An unknown category yields an empty page.',
            ),
        ],
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
