<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use App\CatalogSearch\ApiResource\ProjectMatch;
use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\CatalogSearch\Service\MatchContextResolver;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * @implements ProviderInterface<ProjectMatch>
 */
final class ProjectMatchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly MatchContextResolver $contextResolver,
        private readonly ProjectMatchQueryParser $queryParser,
        private readonly ProjectProductMatchRepository $matches,
        private readonly Pagination $pagination,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|JsonResponse
    {
        $projectContext = $this->contextResolver->resolve($uriVariables['projectId'] ?? null, $uriVariables['contextId'] ?? null);

        if (null !== $response = $this->contextResolver->processingResponse($projectContext)) {
            return $response;
        }

        $query = $this->queryParser->parse($operation);

        [$rawPage, , $rawLimit] = $this->pagination->getPagination($operation, $context);
        $page = $this->toPositiveInt($rawPage);
        $limit = $this->toPositiveInt($rawLimit);

        ['items' => $items, 'total' => $total] = $this->matches->findPageForContext(
            $projectContext->getId(),
            new ProjectMatchCriteria(
                priceMin: $query->priceMin,
                priceMax: $query->priceMax,
                categoryIds: $query->categoryIds,
                sort: $query->sort,
                direction: $query->direction,
                page: $page,
                limit: $limit,
            ),
        );

        return new TraversablePaginator(
            new \ArrayIterator(array_map($this->toDto(...), $items)),
            $page,
            $limit,
            $total,
        );
    }

    /**
     * @return int<1, max>
     */
    private function toPositiveInt(mixed $value): int
    {
        return max(1, is_numeric($value) ? (int) $value : 1);
    }

    private function toDto(ProjectProductMatch $match): ProjectMatch
    {
        $product = $match->getProduct();

        return new ProjectMatch(
            id: (string) $product->getId(),
            title: (string) $product->getTitle(),
            price: $product->getPrice(),
            imageUrl: $this->thumbnailStorage->publicUrl((string) $product->getThumbnailUrl()),
            score: $match->getMatchScore(),
            url: (string) $product->getUrl(),
        );
    }
}
