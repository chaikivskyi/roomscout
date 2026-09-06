<?php

namespace App\Project\Query;

use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\ProjectContextOutputMapper;
use App\Project\Service\ProjectFinder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetProjectContextHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectContextOutputMapper $mapper,
    ) {
    }

    public function __invoke(GetProjectContext $query): ProjectContextOutput
    {
        $project = $this->projects->find($query->projectId);

        $context = $this->contexts->findOneForProject($project->getId(), $query->contextId)
            ?? throw new ProjectContextNotFound();

        return $this->mapper->map($context);
    }
}
