<?php

namespace App\Visualization\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\Response;
use App\Visualization\State\CreateVisualizationProcessor;
use App\Visualization\State\VisualizationItemProvider;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'Visualization', normalizationContext: ['skip_null_values' => false], operations: [
    new Post(
        uriTemplate: '/visualizations',
        securityPostDenormalize: "is_granted('ROLE_USER') and is_granted('CONTEXT_OWNER', object.contextId)",
        status: 201,
        openapi: new Operation(
            tags: ['Visualization / Visualizations'],
            summary: 'Generate a product visualization',
            description: 'Asynchronously renders the matched product into the project\'s latest image version, guided by the context\'s prompt (status starts as "processing"); on completion the result is appended as the project\'s new latest image version. The product must be one of the context\'s matches. Returns 403 for a context belonging to another user, 422 for an unknown context or unmatched product, and 409 while another visualization of the same project is still processing.',
            responses: [
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The context belongs to another user, or the caller is a guest session — visualization requires a registered account.'),
                '409' => new Response(description: 'Another visualization of the same project is still processing.'),
            ],
        ),
        output: VisualizationOutput::class,
        processor: CreateVisualizationProcessor::class,
    ),
    new Get(
        uriTemplate: '/visualizations/{visualizationId}',
        uriVariables: ['visualizationId'],
        requirements: ['visualizationId' => Requirement::UID_RFC4122],
        security: "is_granted('ROLE_USER') and is_granted('VISUALIZATION_OWNER', visualizationId)",
        openapi: new Operation(
            tags: ['Visualization / Visualizations'],
            summary: 'Read a visualization',
            description: 'Poll this after the 201 to observe the generation finishing: `status` moves from "processing" to "completed" or "failed", and `resultImageUrl` is populated once the result has been appended as the project\'s new latest image version. Returns 404 for an unknown visualization, 403 for another user\'s visualization.',
            responses: [
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The visualization belongs to another user, or the caller is a guest session — visualization requires a registered account.'),
                '404' => new Response(description: 'Unknown visualization.'),
            ],
        ),
        output: VisualizationOutput::class,
        provider: VisualizationItemProvider::class,
    ),
])]
final class VisualizationRequest
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $contextId = '';

    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $productId = '';
}
