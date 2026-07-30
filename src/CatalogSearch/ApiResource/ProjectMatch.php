<?php

namespace App\CatalogSearch\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\CatalogSearch\State\ProjectMatchCollectionProvider;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\Type;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/projects/{projectId}/contexts/{contextId}/matches',
        uriVariables: ['projectId', 'contextId'],
        paginationItemsPerPage: 15,
        openapi: new Operation(
            tags: ['CatalogSearch / Matches'],
            summary: 'List catalog products matched to a project context',
            description: 'Products matched to the context\'s prompt + project image query, best match first by default. While matching is still running, responds 202 Accepted with a `Retry-After` header — poll until 200. Only the project owner can list its matches. Each item\'s `id` is the matched product\'s id, not a match id.',
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
                description: 'Category id (UUID); matches in this category or any of its descendants.',
            ),
            'sort' => new QueryParameter(
                schema: ['type' => 'string', 'enum' => ['score', 'price'], 'default' => 'score'],
                description: 'Sort by match score or product price; unpriced products always sort last.',
            ),
            'direction' => new QueryParameter(
                schema: ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
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
