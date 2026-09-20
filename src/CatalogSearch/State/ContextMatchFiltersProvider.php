<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\CatalogSearch\ApiResource\ContextMatchFilters;
use App\CatalogSearch\Query\GetContextMatchFilters;

/**
 * @implements ProviderInterface<ContextMatchFilters>
 */
final class ContextMatchFiltersProvider implements ProviderInterface
{
    public function __construct(
        private readonly MatchFiltersFactory $filters,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ContextMatchFilters
    {
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null);
        $filters = $this->filters->create($operation);

        return $this->queryBus->ask(new GetContextMatchFilters(
            contextId: $contextId,
            filters: $filters,
        ));
    }
}
