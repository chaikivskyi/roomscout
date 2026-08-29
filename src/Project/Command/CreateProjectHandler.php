<?php

namespace App\Project\Command;

use App\Identity\Entity\User;
use App\Project\Entity\Project;
use App\Project\Entity\ProjectContext;
use App\Project\Entity\ProjectImageVersion;
use App\Project\Exception\ProjectOwnerNotFound;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProjectHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(CreateProject $command): void
    {
        $owner = $this->entityManager->find(User::class, $command->ownerId) ?? throw new ProjectOwnerNotFound();

        $project = new Project($owner, $command->projectId);
        $version = new ProjectImageVersion($project, $command->imagePath, $command->versionId);
        $context = new ProjectContext($project, $command->prompt, $command->contextId);

        $this->entityManager->persist($project);
        $this->entityManager->persist($version);
        $this->entityManager->persist($context);
        $this->entityManager->flush();
    }
}
