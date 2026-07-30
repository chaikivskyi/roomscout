<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Project\ApiResource\ProjectContextOutput;
use App\Project\ApiResource\ProjectContextRequest;
use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;

/**
 * @implements ProcessorInterface<ProjectContextRequest, ProjectContextOutput>
 */
final class CreateProjectContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProjectContextOutput
    {
        $project = $this->projectResolver->resolve($uriVariables['projectId'] ?? null);

        $projectContext = new ProjectContext($project, $data->prompt);
        $this->contexts->save($projectContext);

        return new ProjectContextOutput(
            (string) $projectContext->getId(),
            $projectContext->getPrompt(),
            $projectContext->getStatus()->value,
            $projectContext->getCreatedAt(),
        );
    }
}
