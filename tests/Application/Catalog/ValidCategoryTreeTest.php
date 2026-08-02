<?php

namespace App\Tests\Application\Catalog;

use App\Catalog\Entity\Category;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidCategoryTreeTest extends ApiTestCase
{
    /**
     * The subtree-height CTE only sees persisted rows, so the depth limit is
     * enforced against database state — an unflushed category has height 0.
     */
    public function testCategoryMovedUnderTheDeepestAllowedParentIsRejected(): void
    {
        $deepest = $this->chain(Category::MAX_DEPTH);

        $tooDeep = CategoryFactory::createOne(['title' => 'One level too deep']);
        $tooDeep->setParent($deepest);

        $violations = $this->validator()->validate($tooDeep);

        self::assertCount(1, $violations);

        $violation = $violations->get(0);
        self::assertSame('parent', $violation->getPropertyPath());
        self::assertSame('Categories cannot be nested more than 4 levels deep.', (string) $violation->getMessage());
    }

    public function testCategoryOneLevelHigherIsAccepted(): void
    {
        $parent = $this->chain(Category::MAX_DEPTH - 1);

        $child = CategoryFactory::createOne(['title' => 'Still within the limit']);
        $child->setParent($parent);

        self::assertCount(0, $this->validator()->validate($child));
    }

    public function testMovingAnExistingSubtreeBeyondTheLimitIsRejected(): void
    {
        $deepest = $this->chain(Category::MAX_DEPTH - 1);

        // A two-level subtree cannot hang off a parent that is already at depth 3.
        $root = CategoryFactory::createOne(['title' => 'Movable root']);
        CategoryFactory::createOne(['title' => 'Movable child', 'parent' => $root]);

        $root->setParent($deepest);

        self::assertCount(1, $this->validator()->validate($root));
    }

    /**
     * Builds a chain of $depth categories and returns the deepest one.
     */
    private function chain(int $depth): Category
    {
        $current = null;

        for ($level = 1; $level <= $depth; ++$level) {
            $current = CategoryFactory::createOne(['title' => 'Level '.$level, 'parent' => $current]);
        }

        self::assertNotNull($current);

        return $current;
    }

    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get(ValidatorInterface::class);
    }
}
