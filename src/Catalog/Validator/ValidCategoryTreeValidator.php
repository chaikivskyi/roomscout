<?php

namespace App\Catalog\Validator;

use App\Catalog\Entity\Category;
use App\Catalog\Repository\CategoryRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class ValidCategoryTreeValidator extends ConstraintValidator
{
    public function __construct(
        private readonly CategoryRepository $categories,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidCategoryTree) {
            throw new UnexpectedTypeException($constraint, ValidCategoryTree::class);
        }

        if (!$value instanceof Category) {
            throw new UnexpectedValueException($value, Category::class);
        }

        $parent = $value->getParent();
        if (null === $parent) {
            return;
        }

        $parentLevel = 0;
        for ($ancestor = $parent; null !== $ancestor; $ancestor = $ancestor->getParent()) {
            if ($ancestor === $value) {
                $this->context->buildViolation($constraint->cycleMessage)
                    ->atPath('parent')
                    ->addViolation();

                return;
            }

            ++$parentLevel;
        }

        if ($parentLevel + $this->categories->getSubtreeHeight($value) > Category::MAX_DEPTH) {
            $this->context->buildViolation($constraint->depthMessage)
                ->setParameter('{{ max_depth }}', (string) Category::MAX_DEPTH)
                ->atPath('parent')
                ->addViolation();
        }
    }
}
