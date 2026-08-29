<?php

namespace App\CatalogSearch\Query;

use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Dto\ProjectMatchPage;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\CategorySubtreeResolver;
use App\CatalogSearch\Service\MatchContextResolver;
use App\CatalogSearch\Service\ProjectMatchMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListContextMatchesHandler
{
    public function __construct(
        private readonly MatchContextResolver $contextResolver,
        private readonly CategorySubtreeResolver $subtree,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ProjectMatchMapper $mapper,
    ) {
    }

    public function __invoke(ListContextMatches $query): ProjectMatchPage
    {
        $context = $this->contextResolver->resolve($query->projectId, $query->contextId, $query->actorId);

        ['items' => $items, 'total' => $total] = $this->matches->findPageForContext(
            $context->getId(),
            new ProjectMatchCriteria(
                page: $query->page,
                limit: $query->limit,
                priceMin: $query->filters->priceMin,
                priceMax: $query->filters->priceMax,
                categoryIds: $this->subtree->resolve($query->filters->categoryId),
                sort: $query->filters->sort,
                direction: $query->filters->direction,
            ),
        );

        return new ProjectMatchPage(
            array_map($this->mapper->map(...), $items),
            $total,
            $query->page,
            $query->limit,
        );
    }
}
