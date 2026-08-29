<?php

namespace App\Identity\Exception;

use App\Api\Exception\ConflictException;

final class EmailAlreadyRegistered extends ConflictException
{
    public function __construct(
        public readonly string $email,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(sprintf('Email "%s" is already registered.', $email), previous: $previous);
    }
}
