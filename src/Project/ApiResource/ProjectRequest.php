<?php

namespace App\Project\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\Model\Response;
use App\Project\State\CreateProjectProcessor;
use App\Project\State\ProjectCollectionProvider;
use App\Project\State\ProjectItemProvider;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(shortName: 'Project', normalizationContext: ['skip_null_values' => false], operations: [
    new Post(
        uriTemplate: '/projects',
        status: 201,
        inputFormats: ['multipart' => ['multipart/form-data']],
        openapi: new Operation(
            tags: ['Project / Projects'],
            summary: 'Submit a catalog search query (image + prompt)',
            requestBody: new RequestBody(
                description: 'Image file and search prompt',
                content: new \ArrayObject([
                    'multipart/form-data' => new MediaType(schema: new \ArrayObject([
                        'type' => 'object',
                        'required' => ['image', 'prompt'],
                        'properties' => [
                            'image' => [
                                'type' => 'string',
                                'format' => 'binary',
                                'description' => 'JPEG, PNG, WebP or GIF, max 30 MiB',
                            ],
                            'prompt' => ['type' => 'string'],
                        ],
                    ])),
                ]),
                required: true,
            ),
        ),
        output: ProjectOutput::class,
        processor: CreateProjectProcessor::class,
    ),
    new Get(
        uriTemplate: '/projects/{projectId}',
        uriVariables: ['projectId'],
        openapi: new Operation(
            tags: ['Project / Projects'],
            summary: 'Read a project',
            description: 'The project\'s id, creation date and current image — the latest image version, which is the uploaded photo until a placement completes and appends its result. Returns 404 for an unknown project, 403 for another user\'s project.',
            responses: [
                '401' => new Response(description: 'Missing or invalid JWT.'),
                '403' => new Response(description: 'The project belongs to another user.'),
                '404' => new Response(description: 'Unknown project.'),
            ],
        ),
        output: ProjectSummaryOutput::class,
        provider: ProjectItemProvider::class,
    ),
    new GetCollection(
        uriTemplate: '/projects',
        paginationItemsPerPage: 15,
        openapi: new Operation(
            tags: ['Project / Projects'],
            summary: 'List the current user\'s projects',
            description: 'The authenticated user\'s projects, newest first, 15 per page. `prompt` is the project\'s initial context prompt — null once every context has been deleted — and `imageUrl` is the project\'s current image, i.e. its latest image version.',
            responses: [
                '400' => new Response(description: 'Invalid pagination parameter, e.g. a `page` below 1 or non-numeric.'),
                '401' => new Response(description: 'Missing or invalid JWT.'),
            ],
        ),
        output: ProjectListItemOutput::class,
        provider: ProjectCollectionProvider::class,
    ),
])]
final class ProjectRequest
{
    #[Assert\NotNull(message: 'An image file is required.')]
    #[Assert\Image(
        maxSize: '30Mi',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif'],
    )]
    public ?UploadedFile $image = null;

    #[Assert\NotBlank]
    public string $prompt = '';
}
