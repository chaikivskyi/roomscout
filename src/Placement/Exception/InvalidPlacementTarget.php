<?php

namespace App\Placement\Exception;

use App\Api\Exception\UnprocessableEntityException;

final class InvalidPlacementTarget extends UnprocessableEntityException
{
    public static function unknownContext(): self
    {
        return new self('Unknown context for this project.');
    }

    public static function productNotMatched(): self
    {
        return new self('Product is not matched to this context.');
    }
}
