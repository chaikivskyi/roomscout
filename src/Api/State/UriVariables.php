<?php

namespace App\Api\State;

use App\Api\Exception\InvalidUriVariable;
use Symfony\Component\Uid\Uuid;

final class UriVariables
{
    public static function uuid(mixed $value): Uuid
    {
        return Uuids::orNull($value) ?? throw new InvalidUriVariable();
    }
}
