<?php

namespace App\Visualization\Exception;

use App\Api\Exception\UnprocessableEntityException;

final class InvalidVisualizationTarget extends UnprocessableEntityException
{
    public static function unknownContext(): self
    {
        return new self('Unknown context.');
    }

    public static function productNotMatched(): self
    {
        return new self('Product is not matched to this context.');
    }
}
