<?php

namespace App\CatalogSearch\Service;

use App\CatalogSearch\Exception\ContextStillProcessing;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\Uid\Uuid;

final class MatchContextResolver
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function resolve(Uuid $projectId, Uuid $contextId, Uuid $actorId): ProjectContext
    {
        $project = $this->projectResolver->resolve($projectId, $actorId);

        $context = $this->contexts->findOneForProject($project->getId(), $contextId)
            ?? throw new ProjectContextNotFound();

        if (ProjectContextStatus::Processing === $context->getStatus()) {
            throw new ContextStillProcessing();
        }

        return $context;
    }
}
