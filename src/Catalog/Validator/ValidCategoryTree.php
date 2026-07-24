<?php

namespace App\Catalog\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class ValidCategoryTree extends Constraint
{
    public string $cycleMessage = 'A category cannot be nested under itself or one of its descendants.';
    public string $depthMessage = 'Categories cannot be nested more than {{ max_depth }} levels deep.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
