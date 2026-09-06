<?php

namespace App\CatalogSearch\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Catalog\Validator\ValidPriceRange;
use App\CatalogSearch\State\ProjectMatchCollectionProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/projects/{projectId}/contexts/{contextId}/matches',
        uriVariables: ['projectId', 'contextId'],
        requirements: ['projectId' => Requirement::UID_RFC4122, 'contextId' => Requirement::UID_RFC4122],
        security: "is_granted('PROJECT_OWNER', projectId)",
        paginationItemsPerPage: 15,
        openapi: new Operation(
            tags: ['CatalogSearch / Matches'],
            summary: 'List catalog products matched to a project context',
            description: 'Products matched to the context\'s prompt + project image query, best match first by default. While matching is still running, responds 202 Accepted with a problem document and a `Retry-After` header — poll until 200. Only the project owner can list its matches. Each item\'s `id` is the matched product\'s id, not a match id.',
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
                '403' => new Response(description: 'The project belongs to another user.'),
                '404' => new Response(description: 'Unknown project, or unknown context for that project.'),
            ],
        ),
        parameters: [
            'price' => new QueryParameter(
                description: 'Price bounds, e.g. price[gte]=50&price[lte]=200. Products without a price are excluded when either bound is set.',
                constraints: [new All([new Type('numeric'), new GreaterThanOrEqual(0)]), new ValidPriceRange()],
            ),
            'category' => new QueryParameter(
                schema: ['type' => 'string', 'format' => 'uuid'],
                description: 'Category id (UUID); matches in this category or any of its descendants. An unknown category is ignored.',
            ),
            'order' => new QueryParameter(
                schema: ['type' => 'object'],
                description: 'Sort by match score or product price, e.g. order[score]=desc or order[price]=asc. Unpriced products always sort last.',
                constraints: [new All([new Choice(choices: ['asc', 'desc'])])],
            ),
        ],
        provider: ProjectMatchCollectionProvider::class,
    ),
])]
final class ProjectMatch
{
    public function __construct(
        public readonly string $id,
        public readonly string $title,
        public readonly ?float $price,
        public readonly string $imageUrl,
        public readonly float $score,
        public readonly string $url,
    ) {
    }
}
