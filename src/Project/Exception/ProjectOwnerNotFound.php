<?php

namespace App\Project\Exception;

use App\Api\Exception\UnauthorizedException;

final class ProjectOwnerNotFound extends UnauthorizedException
{
    public function __construct()
    {
        parent::__construct('The authenticated user no longer exists.');
    }
}
