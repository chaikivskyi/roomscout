<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectOutput;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function __invoke(GetProject $query): ProjectOutput
    {
        $project = $this->projectResolver->resolve($query->projectId, $query->actorId);

        $context = $this->contexts->findInitialForProject($project->getId());

        return new ProjectOutput(
            (string) $project->getId(),
            $context?->getPrompt(),
            $context?->getStatus()->value,
            $project->getCreatedAt(),
        );
    }
}
