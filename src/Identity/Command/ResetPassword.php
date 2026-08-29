<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;

final class ResetPassword implements CommandInterface
{
    public function __construct(
        #[\SensitiveParameter]
        public readonly string $token,
        #[\SensitiveParameter]
        public readonly string $plainPassword,
    ) {
    }
}
