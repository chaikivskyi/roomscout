<?php

namespace App\Catalog\Service;

use App\Catalog\Repository\CategoryRepository;
use Symfony\Component\Uid\Uuid;

final class CategorySubtreeResolver
{
    public function __construct(
        private readonly CategoryRepository $categories,
    ) {
    }

    /**
     * @return non-empty-list<string>|null
     */
    public function resolve(?Uuid $categoryId): ?array
    {
        if (null === $categoryId) {
            return null;
        }

        return $this->categories->findSubtreeIds($categoryId) ?: null;
    }
}
