<?php

namespace App\Tests\Factory;

use App\Identity\Entity\PasswordResetToken;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<PasswordResetToken>
 */
final class PasswordResetTokenFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return PasswordResetToken::class;
    }

    protected function defaults(): array
    {
        return [
            'user' => UserFactory::new(),
            'tokenHash' => hash('sha256', bin2hex(random_bytes(32))),
            'expiresAt' => new \DateTimeImmutable('+1 hour'),
        ];
    }
}
