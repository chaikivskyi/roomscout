<?php

namespace App\Project\Command;

use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\ProjectFinder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteProjectContextHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectContextRepository $contexts,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteProjectContext $command): void
    {
        $project = $this->projects->find($command->projectId);

        $context = $this->contexts->findOneForProject($project->getId(), $command->contextId)
            ?? throw new ProjectContextNotFound();

        $this->entityManager->remove($context);
        $this->entityManager->flush();
    }
}
