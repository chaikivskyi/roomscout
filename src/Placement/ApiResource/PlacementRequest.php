<?php

namespace App\Placement\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Placement\State\CreatePlacementProcessor;
use App\Placement\State\PlacementItemProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'Placement', normalizationContext: ['skip_null_values' => false], operations: [
    new Post(
        uriTemplate: '/placements',
        securityPostDenormalize: "is_granted('CONTEXT_OWNER', object.contextId)",
        status: 201,
        openapi: new Operation(
            tags: ['Placement / Placements'],
            summary: 'Generate a product placement image',
            description: 'Asynchronously renders the matched product into the project\'s latest image version, guided by the context\'s prompt (status starts as "processing"); on completion the result is appended as the project\'s new latest image version. The product must be one of the context\'s matches. Returns 403 for a context belonging to another user, 422 for an unknown context or unmatched product, and 409 while another placement of the same project is still processing.',
            responses: [
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The context belongs to another user.'),
                '409' => new Response(description: 'Another placement of the same project is still processing.'),
            ],
        ),
        output: PlacementOutput::class,
        processor: CreatePlacementProcessor::class,
    ),
    new Get(
        uriTemplate: '/placements/{placementId}',
        uriVariables: ['placementId'],
        requirements: ['placementId' => Requirement::UID_RFC4122],
        security: "is_granted('PLACEMENT_OWNER', placementId)",
        openapi: new Operation(
            tags: ['Placement / Placements'],
            summary: 'Read a placement',
            description: 'Poll this after the 201 to observe the generation finishing: `status` moves from "processing" to "completed" or "failed", and `resultImageUrl` is populated once the result has been appended as the project\'s new latest image version. Returns 404 for an unknown placement, 403 for another user\'s placement.',
            responses: [
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The placement belongs to another user.'),
                '404' => new Response(description: 'Unknown placement.'),
            ],
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
