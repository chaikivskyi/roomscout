<?php

namespace App\Project\Exception;

use App\Api\Exception\NotFoundException;

final class ProjectNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Project not found.');
    }
}
