<?php

namespace App\Identity\Exception;

use App\Api\Exception\UnprocessableEntityException;

final class InvalidPasswordResetToken extends UnprocessableEntityException
{
    public function __construct()
    {
        parent::__construct('Invalid or expired token.');
    }
}
