<?php

namespace App\Project\Service;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Repository\ProjectRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

final class OwnedProjectResolver
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectRepository $projects,
    ) {
    }

    public function resolve(mixed $projectId): Project
    {
        if (!\is_string($projectId) || !Uuid::isValid($projectId)) {
            throw new NotFoundHttpException('Project not found.');
        }

        $project = $this->projects->find(Uuid::fromString($projectId));

        if (null === $project) {
            throw new NotFoundHttpException('Project not found.');
        }

        $user = $this->security->getUser();

        if (!$user instanceof User || !$project->getUser()->getId()->equals($user->getId())) {
            throw new AccessDeniedException();
        }

        return $project;
    }
}
