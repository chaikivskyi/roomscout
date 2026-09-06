<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectContextOutput;
use App\Project\Query\ListProjectContexts;

/**
 * @implements ProviderInterface<ProjectContextOutput>
 */
final class ProjectContextCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    /**
     * @return list<ProjectContextOutput>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null);

        return $this->queryBus->ask(new ListProjectContexts($projectId));
    }
}
