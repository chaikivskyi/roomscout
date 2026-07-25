<?php

namespace App\Identity\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class UniqueUserEmail extends Constraint
{
    public string $message = 'An account with this email already exists.';
}
