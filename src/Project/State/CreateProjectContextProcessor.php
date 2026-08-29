<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectContextOutput;
use App\Project\ApiResource\ProjectContextRequest;
use App\Project\Command\CreateProjectContext;
use App\Project\Exception\ProjectNotFound;
use App\Project\Query\GetProjectContext;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<ProjectContextRequest, ProjectContextOutput>
 */
final class CreateProjectContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProjectContextOutput
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();
        $actorId = $this->actor->requireCurrentId();
        $contextId = Uuid::v7();

        $this->commandBus->dispatch(new CreateProjectContext($contextId, $projectId, $actorId, $data->prompt));

        return $this->queryBus->ask(new GetProjectContext($projectId, $contextId, $actorId));
    }
}
