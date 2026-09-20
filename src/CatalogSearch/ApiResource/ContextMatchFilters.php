<?php

namespace App\CatalogSearch\ApiResource;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Catalog\Validator\ValidPriceRange;
use App\CatalogSearch\State\ContextMatchFiltersProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new Get(
        uriTemplate: '/catalog-search/contexts/{contextId}/matches/filters',
        uriVariables: ['contextId'],
        requirements: ['contextId' => Requirement::UID_RFC4122],
        security: "is_granted('CONTEXT_OWNER', contextId)",
        openapi: new Operation(
            tags: ['CatalogSearch / Matches'],
            summary: 'List available match filters for a context',
            description: 'The filter facets of the context\'s matches: categories directly holding matched products with their match counts, and the min/max price bounds of priced matches (0–0 when none qualify). The category list always covers every category with a match in the context; the price parameters only narrow the counts (which can drop to 0), while `category` narrows the price bounds (descendants included; an unknown category is ignored) — a facet is never narrowed by its own filter. While matching is still running, responds 202 Accepted with a problem document and a `Retry-After` header — poll until 200. Only the owner of the context\'s project can list its filters.',
            responses: [
                '202' => new Response(
                    description: 'Matching for this context is still running. The body is a problem document, not the resource; poll again after the interval in Retry-After.',
                    content: new \ArrayObject([
                        'application/problem+json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'status' => ['type' => 'integer', 'example' => 202],
                                    'title' => ['type' => 'string', 'example' => 'An error occurred'],
                                    'detail' => ['type' => 'string', 'example' => 'Matching for this context is still running; retry shortly.'],
                                ],
                            ],
                        ],
                    ]),
                    headers: new \ArrayObject([
                        'Retry-After' => [
                            'description' => 'Seconds to wait before polling again.',
                            'schema' => ['type' => 'integer', 'example' => 5],
                        ],
                    ]),
                ),
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The context belongs to another user.'),
                '404' => new Response(description: 'Unknown context.'),
            ],
        ),
        parameters: [
            'price' => new QueryParameter(
                description: 'Price bounds, e.g. price[gte]=50&price[lte]=200. Products without a price are excluded when either bound is set.',
                constraints: [new All([new Type('numeric'), new GreaterThanOrEqual(0)]), new ValidPriceRange()],
            ),
            'category' => new QueryParameter(
                schema: ['type' => 'string', 'format' => 'uuid'],
                description: 'Category id (UUID); narrows the price bounds to this category and its descendants.',
            ),
        ],
        provider: ContextMatchFiltersProvider::class,
    ),
])]
final class ContextMatchFilters
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
