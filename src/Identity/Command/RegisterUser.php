<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class RegisterUser implements CommandInterface
{
    /**
     * @param list<string> $roles
     */
    public function __construct(
        public readonly Uuid $userId,
        public readonly string $email,
        #[\SensitiveParameter]
        public readonly string $plainPassword,
        public readonly array $roles = [],
    ) {
    }
}
