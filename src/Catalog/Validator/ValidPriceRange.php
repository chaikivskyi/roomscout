<?php

namespace App\Catalog\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD)]
final class ValidPriceRange extends Constraint
{
    public string $message = 'min price must not exceed max price.';
}
