<?php

namespace App\CatalogSearch\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\CatalogSearch\State\ProjectMatchFiltersProvider;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new Get(
        uriTemplate: '/projects/{projectId}/contexts/{contextId}/matches/filters',
        uriVariables: ['projectId', 'contextId'],
        openapi: new Operation(
            tags: ['CatalogSearch / Matches'],
            summary: 'List available match filters for a project context',
            description: 'The filter facets of the context\'s matches: categories directly holding matched products with their match counts, and the min/max price bounds of priced matches (0–0 when none qualify). The category list always covers every category with a match in the context; the price parameters only narrow the counts (which can drop to 0), while `category` narrows the price bounds (descendants included; an unknown category is ignored) — a facet is never narrowed by its own filter. While matching is still running, responds 202 Accepted with a `Retry-After` header — poll until 200. Only the project owner can list its filters.',
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
                description: 'Category id (UUID); narrows the price bounds to this category and its descendants.',
            ),
        ],
        provider: ProjectMatchFiltersProvider::class,
    ),
])]
final class ProjectMatchFilters
{
    /**
     * @param list<CategoryFilter> $categories
     */
    public function __construct(
        public readonly string $id,
        #[ApiProperty(genId: false)]
        public readonly array $categories,
        #[ApiProperty(genId: false)]
        public readonly PriceRange $price,
    ) {
    }
}
