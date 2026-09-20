<?php

namespace App\CatalogSearch\Query;

use App\Catalog\Service\CategorySubtreeResolver;
use App\CatalogSearch\Dto\ContextMatchCriteria;
use App\CatalogSearch\Dto\ContextMatchPage;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\ContextMatchMapper;
use App\CatalogSearch\Service\MatchContextResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class ListContextMatchesHandler
{
    public function __construct(
        private readonly MatchContextResolver $contextResolver,
        private readonly CategorySubtreeResolver $subtree,
        private readonly ProjectProductMatchRepository $matches,
        private readonly ContextMatchMapper $mapper,
    ) {
    }

    public function __invoke(ListContextMatches $query): ContextMatchPage
    {
        $context = $this->contextResolver->resolve($query->contextId);

        ['items' => $items, 'total' => $total] = $this->matches->findPageForContext(
            $context->getId(),
            new ContextMatchCriteria(
                page: $query->page,
                limit: $query->limit,
                priceMin: $query->filters->priceMin,
                priceMax: $query->filters->priceMax,
                categoryIds: $this->subtree->resolve($query->filters->categoryId),
                sort: $query->filters->sort,
                direction: $query->filters->direction,
            ),
        );

        return new ContextMatchPage(
            array_map($this->mapper->map(...), $items),
            $total,
            $query->page,
            $query->limit,
        );
    }
}
