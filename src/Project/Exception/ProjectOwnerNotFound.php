<?php

namespace App\Project\Exception;

use App\Api\Exception\NotFoundException;

final class ProjectOwnerNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Project owner no longer exists.');
    }
}
