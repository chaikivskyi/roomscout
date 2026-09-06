<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectSummaryOutput;
use App\Project\Query\GetProject;

/**
 * @implements ProviderInterface<ProjectSummaryOutput>
 */
final class ProjectItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProjectSummaryOutput
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null);

        return $this->queryBus->ask(new GetProject($projectId));
    }
}
