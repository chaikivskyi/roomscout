<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;

final class PromoteUserToAdmin implements CommandInterface
{
    public function __construct(
        public readonly string $email,
    ) {
    }
}
