<?php

namespace App\Visualization\Exception;

use App\Api\Exception\ConflictException;

final class VisualizationAlreadyRunning extends ConflictException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct('A visualization is already being generated for this project.', previous: $previous);
    }
}
