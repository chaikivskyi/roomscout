<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectContextRequest;
use App\Project\Command\DeleteProjectContext;

/**
 * @implements ProcessorInterface<ProjectContextRequest, null>
 */
final class DeleteProjectContextProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null);
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null);

        $this->commandBus->dispatch(new DeleteProjectContext($projectId, $contextId));

        return null;
    }
}
