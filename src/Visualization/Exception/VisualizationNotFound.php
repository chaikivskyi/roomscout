<?php

namespace App\Visualization\Exception;

use App\Api\Exception\NotFoundException;

final class VisualizationNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Visualization not found.');
    }
}
