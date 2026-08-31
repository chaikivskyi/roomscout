<?php

namespace App\Project\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Project\ApiResource\ProjectListItemOutput;
use App\Project\Query\ListProjects;

/**
 * @implements ProviderInterface<ProjectListItemOutput>
 */
final class ProjectCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly QueryBusInterface $queryBus,
        private readonly Pagination $pagination,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        /** @var array{int, int, int} $pagination */
        $pagination = $this->pagination->getPagination($operation, $context);
        [$page, , $limit] = $pagination;

        $result = $this->queryBus->ask(new ListProjects(
            actorId: $this->actor->requireCurrentId(),
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
