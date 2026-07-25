<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Category>
 */
class CategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Category::class);
    }

    /**
     * @return list<int>
     */
    public function findSubtreeIds(int $categoryId): array
    {
        /** @var list<int|numeric-string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id FROM category WHERE id = :id
                UNION ALL
                SELECT c.id FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            SELECT id FROM tree
            SQL, ['id' => $categoryId]);

        return array_map(intval(...), $ids);
    }

    public function getSubtreeHeight(Category $category): int
    {
        if (null === $category->getId()) {
            return 1;
        }

        /** @var int|numeric-string|null $height */
        $height = $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id, 1 AS depth FROM category WHERE id = :id
                UNION ALL
                SELECT c.id, t.depth + 1 FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            SELECT MAX(depth) FROM tree
            SQL, ['id' => $category->getId()]);

        return (int) $height;
    }
}
