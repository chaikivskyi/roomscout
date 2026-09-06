<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\CatalogSearch\ApiResource\ProjectMatch;
use App\CatalogSearch\Query\ListContextMatches;

/**
 * @implements ProviderInterface<ProjectMatch>
 */
final class ProjectMatchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MatchFiltersFactory $filters,
        private readonly QueryBusInterface $queryBus,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null);
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null);

        /** @var array{int, int, int} $pagination */
        $pagination = $this->pagination->getPagination($operation, $context);
        [$page, , $limit] = $pagination;

        $filters = $this->filters->create($operation);

        $result = $this->queryBus->ask(new ListContextMatches(
            projectId: $projectId,
            contextId: $contextId,
            filters: $filters,
            page: $page,
            limit: $limit,
        ));

        return new TraversablePaginator(
            new \ArrayIterator($result->items),
            $result->page,
            $result->limit,
            $result->total,
        );
    }
}
