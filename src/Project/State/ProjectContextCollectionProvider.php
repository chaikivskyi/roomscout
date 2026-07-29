<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;

/**
 * @implements ProviderInterface<ProjectContextOutput>
 */
final class ProjectContextCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    /**
     * @return list<ProjectContextOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $project = $this->projectResolver->resolve($uriVariables['projectId'] ?? null);

        return array_map($this->toDto(...), $this->contexts->findAllForProject($project->getId()));
    }

    private function toDto(ProjectContext $context): ProjectContextOutput
    {
        return new ProjectContextOutput(
            $context->getId()->toRfc4122(),
            $context->getPrompt(),
            $context->getStatus()->value,
            $context->getCreatedAt(),
        );
    }
}
