<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\Project\ApiResource\ProjectSummaryOutput;
use App\Project\Exception\ProjectNotFound;
use App\Project\Query\GetProject;

/**
 * @implements ProviderInterface<ProjectSummaryOutput>
 */
final class ProjectItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProjectSummaryOutput
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();

        return $this->queryBus->ask(new GetProject($projectId, $this->actor->requireCurrentId()));
    }
}
