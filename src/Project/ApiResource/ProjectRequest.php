<?php

namespace App\Project\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\MediaType;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\RequestBody;
use App\Project\State\CreateProjectProcessor;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [
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
                                'description' => 'JPEG, PNG, WebP, GIF or AVIF, max 10 MiB',
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
])]
final class ProjectRequest
{
    #[Assert\NotNull(message: 'An image file is required.')]
    #[Assert\Image(
        maxSize: '10Mi',
        mimeTypes: ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'],
    )]
    public ?UploadedFile $image = null;

    #[Assert\NotBlank]
    public string $prompt = '';
}
