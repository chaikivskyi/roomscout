<?php

namespace App\Project\Command;

use App\Project\Exception\ProjectContextNotFound;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class DeleteProjectContextHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(DeleteProjectContext $command): void
    {
        $project = $this->projectResolver->resolve($command->projectId, $command->actorId);

        $context = $this->contexts->findOneForProject($project->getId(), $command->contextId)
            ?? throw new ProjectContextNotFound();

        $this->entityManager->remove($context);
        $this->entityManager->flush();
    }
}
