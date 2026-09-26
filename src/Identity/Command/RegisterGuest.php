<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class RegisterGuest implements CommandInterface
{
    public function __construct(
        public readonly Uuid $userId,
    ) {
    }
}
