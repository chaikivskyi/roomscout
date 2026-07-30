<?php

namespace App\Tests\Application\Catalog;

use App\Catalog\Entity\Category;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\CategoryFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ValidCategoryTreeTest extends ApiTestCase
{
    /**
     * A brand-new Category matches no rows in the subtree-height CTE. Its height is still
     * 1, and reporting 0 would let the tree grow one level past Category::MAX_DEPTH.
     */
    public function testNewCategoryUnderTheDeepestAllowedParentIsRejected(): void
    {
        $deepest = $this->chain(Category::MAX_DEPTH);

        $tooDeep = new Category();
        $tooDeep->setTitle('One level too deep');
        $tooDeep->setParent($deepest);

        $violations = $this->validator()->validate($tooDeep);

        self::assertCount(1, $violations);

        $violation = $violations->get(0);
        self::assertSame('parent', $violation->getPropertyPath());
        self::assertSame('Categories cannot be nested more than 4 levels deep.', (string) $violation->getMessage());
    }

    public function testNewCategoryOneLevelHigherIsAccepted(): void
    {
        $parent = $this->chain(Category::MAX_DEPTH - 1);

        $child = new Category();
        $child->setTitle('Still within the limit');
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
