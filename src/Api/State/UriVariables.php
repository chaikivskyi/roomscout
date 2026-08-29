<?php

namespace App\Api\State;

use Symfony\Component\Uid\Uuid;

final class UriVariables
{
    public static function uuid(mixed $value): ?Uuid
    {
        return \is_string($value) && Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }
}
