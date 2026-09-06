<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Exception\ContextStillProcessing;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\ProjectFinder;
use Symfony\Component\Uid\Uuid;

final class MatchContextResolver
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function resolve(Uuid $projectId, Uuid $contextId): ProjectContext
    {
        $project = $this->projects->find($projectId);

        $context = $this->contexts->findOneForProject($project->getId(), $contextId)
            ?? throw new ProjectContextNotFound();

        if (ProjectContextStatus::Processing === $context->getStatus()) {
            throw new ContextStillProcessing();
        }

        return $context;
    }
}
