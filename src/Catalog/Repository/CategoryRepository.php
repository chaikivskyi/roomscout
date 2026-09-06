<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

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
     * @return list<string>
     */
    public function findSubtreeIds(Uuid $categoryId): array
    {
        /** @var list<string> $ids */
        $ids = $this->getEntityManager()->getConnection()->fetchFirstColumn(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id FROM category WHERE id = :id
                UNION ALL
                SELECT c.id FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            SELECT id FROM tree
            SQL, ['id' => (string) $categoryId]);

        return $ids;
    }

    public function getSubtreeHeight(Category $category): int
    {
        /** @var int|numeric-string|null $height */
        $height = $this->getEntityManager()->getConnection()->fetchOne(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id, 1 AS depth FROM category WHERE id = :id
                UNION ALL
                SELECT c.id, t.depth + 1 FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            SELECT MAX(depth) FROM tree
            SQL, ['id' => (string) $category->getId()]);

        return (int) $height;
    }
}
