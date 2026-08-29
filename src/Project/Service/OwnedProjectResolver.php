<?php

namespace App\Project\Service;

use App\Project\Entity\Project;
use App\Project\Exception\ProjectNotFound;
use App\Project\Exception\ProjectNotOwned;
use App\Project\Repository\ProjectRepository;
use Symfony\Component\Uid\Uuid;

final class OwnedProjectResolver
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {
    }

    public function resolve(Uuid $projectId, Uuid $actorId): Project
    {
        $project = $this->projects->find($projectId) ?? throw new ProjectNotFound();

        if (!$project->getUser()->getId()->equals($actorId)) {
            throw new ProjectNotOwned();
        }

        return $project;
    }
}
