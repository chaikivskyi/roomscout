<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\CatalogSearch\ApiResource\CategoryFilter;
use App\CatalogSearch\ApiResource\PriceRange;
use App\CatalogSearch\ApiResource\ProjectMatchFilters;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\MatchContextResolver;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @implements ProviderInterface<ProjectMatchFilters>
 */
final class ProjectMatchFiltersProvider implements ProviderInterface
{
    public function __construct(
        private readonly MatchContextResolver $contextResolver,
        private readonly ProjectMatchQueryParser $queryParser,
        private readonly ProjectProductMatchRepository $matches,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ProjectMatchFilters|JsonResponse
    {
        $projectContext = $this->contextResolver->resolve($uriVariables['projectId'] ?? null, $uriVariables['contextId'] ?? null);

        if (null !== $response = $this->contextResolver->processingResponse($projectContext)) {
            return $response;
        }

        $query = $this->queryParser->parse($operation);

        $categories = array_map(
            static fn (array $row) => new CategoryFilter($row['id'], $row['title'], $row['count']),
            $this->matches->countByCategoryForContext($projectContext->getId(), $query->priceMin, $query->priceMax),
        );

        $range = $this->matches->findPriceRangeForContext(
            $projectContext->getId(),
            $query->categoryIds,
        );

        return new ProjectMatchFilters(
            id: (string) $projectContext->getId(),
            categories: $categories,
            price: null === $range
                ? new PriceRange(0, 0)
                : new PriceRange((int) floor($range['min']), (int) ceil($range['max'])),
        );
    }
}
