<?php

namespace App\Placement\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Placement\State\CreatePlacementProcessor;
use App\Placement\State\PlacementItemProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'Placement', normalizationContext: ['skip_null_values' => false], operations: [
    new Post(
        uriTemplate: '/projects/{projectId}/placements',
        uriVariables: ['projectId'],
        status: 201,
        openapi: new Operation(
            tags: ['Placement / Placements'],
            summary: 'Generate a product placement image',
            description: 'Asynchronously renders the matched product into the project\'s latest image version, guided by the context\'s prompt (status starts as "processing"); on completion the result is appended as the project\'s new latest image version. The product must be one of the context\'s matches. Returns 404 for an unknown project, 403 for another user\'s project, 422 for an unknown context or unmatched product, and 409 while another placement of the project is still processing.',
        ),
        output: PlacementOutput::class,
        processor: CreatePlacementProcessor::class,
    ),
    new Get(
        uriTemplate: '/projects/{projectId}/placements/{placementId}',
        uriVariables: ['projectId', 'placementId'],
        openapi: new Operation(
            tags: ['Placement / Placements'],
            summary: 'Read a placement',
            description: 'Poll this after the 201 to observe the generation finishing: `status` moves from "processing" to "completed" or "failed", and `resultImageUrl` is populated once the result has been appended as the project\'s new latest image version. Returns 404 for an unknown project or placement, 403 for another user\'s project.',
        ),
        output: PlacementOutput::class,
        provider: PlacementItemProvider::class,
    ),
])]
final class PlacementRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $contextId = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $productId = '';
}
