<?php

namespace App\Identity\Validator;

use App\Identity\Api\UserRepositoryInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

final class UniqueUserEmailValidator extends ConstraintValidator
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
    ) {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueUserEmail) {
            throw new UnexpectedTypeException($constraint, UniqueUserEmail::class);
        }

        if (empty($value)) {
            return;
        }

        if (null !== $this->users->findOneByEmail((string) $value)) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
