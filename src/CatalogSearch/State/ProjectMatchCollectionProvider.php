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
use App\Project\Repository\ProjectRepository;
use League\Flysystem\FilesystemOperator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProviderInterface<ProjectMatch>
 */
final class ProjectMatchCollectionProvider implements ProviderInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProjectRepository $projects,
        private readonly ProjectProductMatchRepository $matches,
        private readonly CategoryRepository $categories,
        private readonly Pagination $pagination,
        #[Autowire(service: 'product_thumbnails.storage')]
        private readonly FilesystemOperator $thumbnailStorage,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $projectId = (string) ($uriVariables['projectId'] ?? '');

        if (!Uuid::isValid($projectId)) {
            throw new NotFoundHttpException('Project not found.');
        }

        $project = $this->projects->find(Uuid::fromString($projectId));

        if (null === $project) {
            throw new NotFoundHttpException('Project not found.');
        }

        $user = $this->security->getUser();

        if ($project->getUser()->getId() !== $user->getId()) {
            throw new AccessDeniedException();
        }

        $priceMin = $this->floatParameter($operation, 'priceMin');
        $priceMax = $this->floatParameter($operation, 'priceMax');

        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new UnprocessableEntityHttpException('min price must not exceed max price.');
        }

        [$page, , $limit] = $this->pagination->getPagination($operation, $context);

        $categoryIds = null;
        $categoryId = $this->parameter($operation, 'category');

        if (null !== $categoryId) {
            $categoryIds = $this->categories->findSubtreeIds((int) $categoryId);

            if ([] === $categoryIds) {
                return new TraversablePaginator(new \ArrayIterator([]), $page, $limit, 0);
            }
        }

        $sort = $this->parameter($operation, 'sort');
        $direction = $this->parameter($operation, 'direction');

        ['items' => $items, 'total' => $total] = $this->matches->findPageForProject(
            $project->getId(),
            new ProjectMatchCriteria(
                priceMin: $priceMin,
                priceMax: $priceMax,
                categoryIds: $categoryIds,
                sort: null === $sort ? MatchSort::Score : MatchSort::from((string) $sort),
                direction: null === $direction ? SortDirection::Desc : SortDirection::from((string) $direction),
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

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }

    private function floatParameter(Operation $operation, string $name): ?float
    {
        $value = $this->parameter($operation, $name);

        return null === $value ? null : (float) $value;
    }

    private function toDto(ProjectProductMatch $match): ProjectMatch
    {
        $product = $match->getProduct();

        return new ProjectMatch(
            id: $product->getUuid()->toRfc4122(),
            title: (string) $product->getTitle(),
            price: $product->getPrice(),
            imageUrl: $this->thumbnailStorage->publicUrl((string) $product->getThumbnailUrl()),
            score: $match->getMatchScore(),
            url: (string) $product->getUrl(),
        );
    }
}
