<?php

namespace App\Api\State;

use Symfony\Component\Uid\Uuid;

final class Uuids
{
    public static function orNull(mixed $value): ?Uuid
    {
        if ($value instanceof Uuid) {
            return $value;
        }

        return \is_string($value) && Uuid::isValid($value) ? Uuid::fromString($value) : null;
    }
}
