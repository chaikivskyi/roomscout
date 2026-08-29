<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectContextRequest;
use App\Project\Command\DeleteProjectContext;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Exception\ProjectNotFound;

/**
 * @implements ProcessorInterface<ProjectContextRequest, null>
 */
final class DeleteProjectContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null) ?? throw new ProjectContextNotFound();

        $this->commandBus->dispatch(new DeleteProjectContext($projectId, $contextId, $this->actor->requireCurrentId()));

        return null;
    }
}
