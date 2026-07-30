<?php

namespace App\Tests\Application\Catalog;

use App\Catalog\Entity\Category;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;

/**
 * The descendant propagation runs as a raw recursive CTE in postFlush, binding the changed
 * root ids as an array parameter. With UUID keys those have to be bound as strings, or
 * Postgres rejects the `id IN (:ids)` comparison outright.
 */
final class SyncCategoryTreeListenerTest extends ApiTestCase
{
    public function testRenamingARootRewritesDescendantPaths(): void
    {
        $root = CategoryFactory::createOne(['title' => 'Furniture']);
        $child = CategoryFactory::createOne(['title' => 'Seating', 'parent' => $root]);
        $grandchild = CategoryFactory::createOne(['title' => 'Armchairs', 'parent' => $child]);

        self::assertSame('Furniture › Seating › Armchairs', $grandchild->getPathTitle());

        $root->setTitle('Home furniture');
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame('Home furniture', $this->reload($root)->getPathTitle());
        self::assertSame('Home furniture › Seating', $this->reload($child)->getPathTitle());
        self::assertSame('Home furniture › Seating › Armchairs', $this->reload($grandchild)->getPathTitle());
    }

    public function testMovingASubtreeRewritesLevelsAndPathsOfDescendants(): void
    {
        $furniture = CategoryFactory::createOne(['title' => 'Furniture']);
        $storage = CategoryFactory::createOne(['title' => 'Storage']);
        $shelves = CategoryFactory::createOne(['title' => 'Shelves', 'parent' => $storage]);
        $floating = CategoryFactory::createOne(['title' => 'Floating', 'parent' => $shelves]);

        self::assertSame(1, $storage->getLevel());
        self::assertSame(3, $floating->getLevel());

        $storage->setParent($furniture);
        $this->entityManager()->flush();
        $this->entityManager()->clear();

        self::assertSame(2, $this->reload($storage)->getLevel());
        self::assertSame('Furniture › Storage', $this->reload($storage)->getPathTitle());
        self::assertSame(3, $this->reload($shelves)->getLevel());
        self::assertSame(4, $this->reload($floating)->getLevel());
        self::assertSame('Furniture › Storage › Shelves › Floating', $this->reload($floating)->getPathTitle());
    }

    private function reload(Category $category): Category
    {
        $fresh = $this->entityManager()->find(Category::class, $category->getId());
        self::assertNotNull($fresh);

        return $fresh;
    }
}
