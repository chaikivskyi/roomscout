<?php

namespace App\Project\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use ApiPlatform\OpenApi\Model\Operation;
use App\Project\State\CreateProjectContextProcessor;
use App\Project\State\DeleteProjectContextProcessor;
use App\Project\State\ProjectContextCollectionProvider;
use Symfony\Component\Validator\Constraints as Assert;

#[ApiResource(operations: [
    new GetCollection(
        uriTemplate: '/projects/{projectId}/contexts',
        uriVariables: ['projectId'],
        paginationEnabled: false,
        openapi: new Operation(
            tags: ['Project / Contexts'],
            summary: 'List a project\'s contexts',
            description: 'The project\'s prompt contexts in creation order, oldest first. Returns 404 for an unknown project, 403 for another user\'s project.',
        ),
        output: ProjectContextOutput::class,
        provider: ProjectContextCollectionProvider::class,
    ),
    new Post(
        uriTemplate: '/projects/{projectId}/contexts',
        uriVariables: ['projectId'],
        status: 201,
        openapi: new Operation(
            tags: ['Project / Contexts'],
            summary: 'Add a prompt context to a project',
            description: 'Creates a new prompt variation of the project and matches it against the catalog asynchronously (status starts as "processing"). Returns 404 for an unknown project, 403 for another user\'s project.',
        ),
        output: ProjectContextOutput::class,
        processor: CreateProjectContextProcessor::class,
    ),
    new Delete(
        uriTemplate: '/projects/{projectId}/contexts/{contextId}',
        uriVariables: ['projectId', 'contextId'],
        status: 204,
        openapi: new Operation(
            tags: ['Project / Contexts'],
            summary: 'Delete a project context',
            description: 'Removes the context together with its product matches. Returns 404 for an unknown project or context, 403 for another user\'s project.',
        ),
        output: false,
        read: false,
        processor: DeleteProjectContextProcessor::class,
    ),
])]
final class ProjectContextRequest
{
    #[Assert\NotBlank]
    public string $prompt = '';
}
