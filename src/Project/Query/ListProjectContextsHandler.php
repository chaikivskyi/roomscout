<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\ProjectContextOutputMapper;
use App\Project\Service\ProjectFinder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListProjectContextsHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectContextOutputMapper $mapper,
    ) {
    }

    /**
     * @return list<ProjectContextOutput>
     */
    public function __invoke(ListProjectContexts $query): array
    {
        $project = $this->projects->find($query->projectId);

        return array_map($this->mapper->map(...), $this->contexts->findAllForProject($project->getId()));
    }
}
