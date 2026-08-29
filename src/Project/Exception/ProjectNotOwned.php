<?php

namespace App\Project\Exception;

use App\Api\Exception\AccessDeniedException;

final class ProjectNotOwned extends AccessDeniedException
{
    public function __construct()
    {
        parent::__construct('Access Denied.');
    }
}
