<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use App\Project\Service\ProjectContextOutputMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProjectContextsHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectContextOutputMapper $mapper,
    ) {
    }

    /**
     * @return list<ProjectContextOutput>
     */
    public function __invoke(ListProjectContexts $query): array
    {
        $project = $this->projectResolver->resolve($query->projectId, $query->actorId);

        return array_map($this->mapper->map(...), $this->contexts->findAllForProject($project->getId()));
    }
}
