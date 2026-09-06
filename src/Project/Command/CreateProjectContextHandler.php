<?php

namespace App\Project\Command;

use App\Project\Entity\ProjectContext;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\ProjectFinder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class CreateProjectContextHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProjectContextRepository $contexts,
    ) {
    }

    public function __invoke(CreateProjectContext $command): void
    {
        $project = $this->projects->find($command->projectId);

        $this->contexts->save(new ProjectContext($project, $command->prompt, $command->contextId));
    }
}
