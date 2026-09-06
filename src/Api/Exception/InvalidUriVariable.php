<?php

namespace App\Api\Exception;

final class InvalidUriVariable extends NotFoundException
{
    public function __construct()
    {
        parent::__construct('Not found.');
    }
}
