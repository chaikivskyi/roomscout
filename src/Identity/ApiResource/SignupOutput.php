<?php

namespace App\Identity\ApiResource;

use Symfony\Component\Serializer\Attribute\Groups;

final class SignupOutput
{
    public function __construct(
        #[Groups(['signup:read'])]
        public readonly string $id,
        #[Groups(['signup:read'])]
        public readonly string $email,
        #[Groups(['signup:read'])]
        #[\SensitiveParameter]
        public readonly string $token,
    ) {
    }
}
