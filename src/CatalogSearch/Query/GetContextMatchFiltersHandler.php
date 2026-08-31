<?php

namespace App\CatalogSearch\Query;

use App\Catalog\Service\CategorySubtreeResolver;
use App\CatalogSearch\ApiResource\CategoryFilter;
use App\CatalogSearch\ApiResource\PriceRange;
use App\CatalogSearch\ApiResource\ProjectMatchFilters;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\MatchContextResolver;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetContextMatchFiltersHandler
{
    public function __construct(
        private readonly MatchContextResolver $contextResolver,
        private readonly CategorySubtreeResolver $subtree,
        private readonly ProjectProductMatchRepository $matches,
    ) {
    }

    public function __invoke(GetContextMatchFilters $query): ProjectMatchFilters
    {
        $context = $this->contextResolver->resolve($query->projectId, $query->contextId, $query->actorId);

        $categories = array_map(
            static fn (array $row) => new CategoryFilter($row['id'], $row['title'], $row['count']),
            $this->matches->countByCategoryForContext($context->getId(), $query->filters->priceMin, $query->filters->priceMax),
        );

        $range = $this->matches->findPriceRangeForContext(
            $context->getId(),
            $this->subtree->resolve($query->filters->categoryId),
        );

        return new ProjectMatchFilters(
            id: (string) $context->getId(),
            categories: $categories,
            price: null === $range
                ? new PriceRange(0, 0)
                : new PriceRange((int) floor($range['min']), (int) ceil($range['max'])),
        );
    }
}
