<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class ClaimGuestAccount implements CommandInterface
{
    public function __construct(
        public readonly Uuid $guestId,
        public readonly string $email,
        #[\SensitiveParameter]
        public readonly string $plainPassword,
    ) {
    }
}
