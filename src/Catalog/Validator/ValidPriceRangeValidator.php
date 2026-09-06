<?php

namespace App\Catalog\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class ValidPriceRangeValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof ValidPriceRange) {
            throw new UnexpectedTypeException($constraint, ValidPriceRange::class);
        }

        if (!\is_array($value)) {
            return;
        }

        $lower = $value['gte'] ?? $value['gt'] ?? null;
        $upper = $value['lte'] ?? $value['lt'] ?? null;

        if (!is_numeric($lower) || !is_numeric($upper)) {
            return;
        }

        if ((float) $lower <= (float) $upper) {
            return;
        }

        $this->context->buildViolation($constraint->message)->addViolation();
    }
}
