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
     * Height of the subtree rooted at the given category: a leaf (or an
     * unsaved category, which cannot have children yet) is 1.
     */
    public function getSubtreeHeight(Category $category): int
    {
        if (null === $category->getId()) {
            return 1;
        }

        return (int) $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id, 1 AS depth FROM category WHERE id = :id
                UNION ALL
                SELECT c.id, t.depth + 1 FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            SELECT MAX(depth) FROM tree
            SQL, ['id' => $category->getId()]);
    }
}
