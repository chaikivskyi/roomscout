<?php

namespace App\Placement\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Placement\State\CreatePlacementProcessor;
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
