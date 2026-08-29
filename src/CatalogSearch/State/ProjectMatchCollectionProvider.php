<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\CatalogSearch\ApiResource\ProjectMatch;
use App\CatalogSearch\Query\ListContextMatches;
use App\Project\Exception\ProjectContextNotFound;
use App\Project\Exception\ProjectNotFound;

/**
 * @implements ProviderInterface<ProjectMatch>
 */
final class ProjectMatchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly MatchFiltersParser $filtersParser,
        private readonly QueryBusInterface $queryBus,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();
        $contextId = UriVariables::uuid($uriVariables['contextId'] ?? null) ?? throw new ProjectContextNotFound();

        [$rawPage, , $rawLimit] = $this->pagination->getPagination($operation, $context);

        $filters = $this->filtersParser->parse($operation);

        $result = $this->queryBus->ask(new ListContextMatches(
            projectId: $projectId,
            contextId: $contextId,
            actorId: $this->actor->requireCurrentId(),
            filters: $filters,
            page: $this->toPositiveInt($rawPage),
            limit: $this->toPositiveInt($rawLimit),
        ));

        return new TraversablePaginator(
            new \ArrayIterator($result->items),
            $result->page,
            $result->limit,
            $result->total,
        );
    }

    /**
     * @return int<1, max>
     */
    private function toPositiveInt(mixed $value): int
    {
        return max(1, is_numeric($value) ? (int) $value : 1);
    }
}
