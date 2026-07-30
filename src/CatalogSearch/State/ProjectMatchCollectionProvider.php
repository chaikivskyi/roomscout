<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\Pagination;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ParameterNotFound;
use ApiPlatform\State\ProviderInterface;
use App\Catalog\Repository\CategoryRepository;
use App\CatalogSearch\ApiResource\ProjectMatch;
use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Entity\ProjectProductMatch;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;
use App\CatalogSearch\Repository\ProjectProductMatchRepository;
use App\Project\Entity\ProjectContext;
use App\Project\Enum\ProjectContextStatus;
use App\Project\Repository\ProjectContextRepository;
use App\Project\Service\OwnedProjectResolver;
use League\Flysystem\FilesystemOperator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProjectMatch>
 */
final class ProjectMatchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly OwnedProjectResolver $projectResolver,
        private readonly ProjectContextRepository $contexts,
        private readonly ProjectProductMatchRepository $matches,
        private readonly CategoryRepository $categories,
        private readonly Pagination $pagination,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|JsonResponse
    {
        $project = $this->projectResolver->resolve($uriVariables['projectId'] ?? null);
        $projectContext = $this->resolveContext($project->getId(), $uriVariables['contextId'] ?? null);

        if (ProjectContextStatus::Processing === $projectContext->getStatus()) {
            return new JsonResponse(
                ['status' => ProjectContextStatus::Processing->value],
                Response::HTTP_ACCEPTED,
                ['Retry-After' => '5'],
            );
        }

        $priceMin = $this->parameter($operation, 'priceMin');
        $priceMin = \is_int($priceMin) ? $priceMin : null;
        $priceMax = $this->parameter($operation, 'priceMax');
        $priceMax = \is_int($priceMax) ? $priceMax : null;

        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new UnprocessableEntityHttpException('min price must not exceed max price.');
        }

        [$rawPage, , $rawLimit] = $this->pagination->getPagination($operation, $context);
        $page = $this->toPositiveInt($rawPage);
        $limit = $this->toPositiveInt($rawLimit);

        $categoryIds = null;
        $categoryId = $this->parameter($operation, 'category');

        if (\is_string($categoryId) && Uuid::isValid($categoryId)) {
            $categoryIds = $this->categories->findSubtreeIds(Uuid::fromString($categoryId));

            if ([] === $categoryIds) {
                return new TraversablePaginator(new \ArrayIterator([]), $page, $limit, 0);
            }
        }

        $sort = $this->parameter($operation, 'sort');
        $direction = $this->parameter($operation, 'direction');

        ['items' => $items, 'total' => $total] = $this->matches->findPageForContext(
            $projectContext->getId(),
            new ProjectMatchCriteria(
                priceMin: $priceMin,
                priceMax: $priceMax,
                categoryIds: $categoryIds,
                sort: \is_string($sort) ? MatchSort::from($sort) : MatchSort::Score,
                direction: \is_string($direction) ? SortDirection::from($direction) : SortDirection::Desc,
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

    private function resolveContext(Uuid $projectId, mixed $contextId): ProjectContext
    {
        if (!\is_string($contextId) || !Uuid::isValid($contextId)) {
            throw new NotFoundHttpException('Context not found.');
        }

        $projectContext = $this->contexts->findOneForProject($projectId, Uuid::fromString($contextId));

        if (null === $projectContext) {
            throw new NotFoundHttpException('Context not found.');
        }

        return $projectContext;
    }

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
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
