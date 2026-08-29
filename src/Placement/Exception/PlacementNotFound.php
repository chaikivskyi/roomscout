<?php

namespace App\Placement\Exception;

use App\Api\Exception\NotFoundException;

final class PlacementNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Placement not found.');
    }
}
