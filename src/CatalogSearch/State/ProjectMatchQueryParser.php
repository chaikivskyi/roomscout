<?php

namespace App\CatalogSearch\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ParameterNotFound;
use App\Catalog\Repository\CategoryRepository;
use App\CatalogSearch\Dto\ProjectMatchQuery;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Uid\Uuid;

final class ProjectMatchQueryParser
{
    public function __construct(
        private readonly CategoryRepository $categories,
    ) {
    }

    public function parse(Operation $operation): ProjectMatchQuery
    {
        $priceMin = $this->parameter($operation, 'priceMin');
        $priceMin = \is_int($priceMin) ? $priceMin : null;
        $priceMax = $this->parameter($operation, 'priceMax');
        $priceMax = \is_int($priceMax) ? $priceMax : null;

        if (null !== $priceMin && null !== $priceMax && $priceMin > $priceMax) {
            throw new UnprocessableEntityHttpException('min price must not exceed max price.');
        }

        $categoryIds = null;
        $categoryId = $this->parameter($operation, 'category');

        if (\is_string($categoryId) && Uuid::isValid($categoryId)) {
            $categoryIds = $this->categories->findSubtreeIds(Uuid::fromString($categoryId)) ?: null;
        }

        $sort = $this->parameter($operation, 'sort');
        $direction = $this->parameter($operation, 'direction');

        return new ProjectMatchQuery(
            priceMin: $priceMin,
            priceMax: $priceMax,
            categoryIds: $categoryIds,
            sort: \is_string($sort) ? MatchSort::from($sort) : MatchSort::Score,
            direction: \is_string($direction) ? SortDirection::from($direction) : SortDirection::Desc,
        );
    }

    private function parameter(Operation $operation, string $name): mixed
    {
        $value = $operation->getParameters()?->get($name)?->getValue();

        return $value instanceof ParameterNotFound ? null : $value;
    }
}
