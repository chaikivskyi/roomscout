<?php

namespace App\Project\Service;

use App\Project\Entity\Project;
use App\Project\Exception\ProjectNotFound;
use App\Project\Repository\ProjectRepository;
use Symfony\Component\Uid\Uuid;

final class ProjectFinder
{
    public function __construct(
        private readonly ProjectRepository $projects,
    ) {
    }

    public function find(Uuid $projectId): Project
    {
        return $this->projects->find($projectId) ?? throw new ProjectNotFound();
    }
}
