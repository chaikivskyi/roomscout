<?php

namespace App\CatalogSearch\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\QueryParameter;
use ApiPlatform\OpenApi\Model\Operation;
use App\CatalogSearch\State\ProjectMatchCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/projects/{projectId}/matches',
        uriVariables: ['projectId'],
        paginationItemsPerPage: 15,
        openapi: new Operation(
            tags: ['CatalogSearch / Matches'],
            summary: 'List catalog products matched to a project',
            description: 'Products matched to the project\'s image + prompt query, best match first by default. Only the project owner can list its matches. Each item\'s `id` is the matched product\'s id, not a match id.',
        ),
        parameters: [
            'priceMin' => new QueryParameter(
                schema: ['type' => 'number', 'minimum' => 0],
                description: 'Lowest product price to include; products without a price are excluded when set.',
                constraints: [new Assert\Type('numeric'), new Assert\PositiveOrZero()],
            ),
            'priceMax' => new QueryParameter(
                schema: ['type' => 'number', 'minimum' => 0],
                description: 'Highest product price to include; products without a price are excluded when set.',
                constraints: [new Assert\Type('numeric'), new Assert\PositiveOrZero()],
            ),
            'category' => new QueryParameter(
                schema: ['type' => 'integer', 'minimum' => 1],
                description: 'Category id; matches in this category or any of its descendants.',
                constraints: [new Assert\Regex(pattern: '/^[1-9]\d*$/', message: 'This value should be a positive integer.')],
            ),
            'sort' => new QueryParameter(
                schema: ['type' => 'string', 'enum' => ['score', 'price'], 'default' => 'score'],
                description: 'Sort by match score or product price; unpriced products always sort last.',
                constraints: [new Assert\Choice(choices: ['score', 'price'])],
            ),
            'direction' => new QueryParameter(
                schema: ['type' => 'string', 'enum' => ['asc', 'desc'], 'default' => 'desc'],
                constraints: [new Assert\Choice(choices: ['asc', 'desc'])],
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
