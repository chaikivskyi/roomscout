<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectSummaryOutput;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
    ) {
    }

    public function __invoke(GetProject $query): ProjectSummaryOutput
    {
        $project = $this->projectResolver->resolve($query->projectId, $query->actorId);

        return new ProjectSummaryOutput(
            (string) $project->getId(),
            $project->getCreatedAt(),
        );
    }
}
