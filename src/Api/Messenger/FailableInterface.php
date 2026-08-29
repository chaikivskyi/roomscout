<?php

namespace App\Api\Messenger;

interface FailableInterface
{
    public function markFailed(): bool;
}
