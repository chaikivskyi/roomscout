<?php

namespace App\Placement\Exception;

use App\Api\Exception\ConflictException;

final class PlacementAlreadyRunning extends ConflictException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('A placement is already being generated for this project.', previous: $previous);
    }
}
