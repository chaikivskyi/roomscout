<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use App\Project\Service\ProjectContextOutputMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectContextHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectContextOutputMapper $mapper,
    ) {
    }

    public function __invoke(GetProjectContext $query): ProjectContextOutput
    {
        $project = $this->projectResolver->resolve($query->projectId, $query->actorId);

        $context = $this->contexts->findOneForProject($project->getId(), $query->contextId)
            ?? throw new ProjectContextNotFound();

        return $this->mapper->map($context);
    }
}
