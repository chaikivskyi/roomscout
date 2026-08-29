<?php

namespace App\Project\Exception;

use App\Api\Exception\NotFoundException;

final class ProjectContextNotFound extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Context not found.');
    }
}
