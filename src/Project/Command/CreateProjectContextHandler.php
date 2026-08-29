<?php

namespace App\Project\Command;

use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProjectContextHandler
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function __invoke(CreateProjectContext $command): void
    {
        $project = $this->projectResolver->resolve($command->projectId, $command->actorId);

        $this->contexts->save(new ProjectContext($project, $command->prompt, $command->contextId));
    }
}
