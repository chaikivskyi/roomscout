<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\CatalogSearch\ApiResource\ProjectMatchFilters;
use App\CatalogSearch\Query\GetContextMatchFilters;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Exception\ProjectNotFound;

/**
 * @implements ProviderInterface<ProjectMatchFilters>
 */
final class ProjectMatchFiltersProvider implements ProviderInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly MatchFiltersParser $filtersParser,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProjectMatchFilters
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null) ?? throw new ProjectContextNotFound();
        $filters = $this->filtersParser->parse($operation);

        return $this->queryBus->ask(new GetContextMatchFilters(
            projectId: $projectId,
            contextId: $contextId,
            actorId: $this->actor->requireCurrentId(),
            filters: $filters,
        ));
    }
}
