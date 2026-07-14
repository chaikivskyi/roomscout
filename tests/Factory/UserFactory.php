<?php

namespace App\Tests\Factory;

use App\Identity\Entity\User;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public const string DEFAULT_PASSWORD = 'Password123';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    public static function class(): string
    {
        return User::class;
    }

    protected function defaults(): array
    {
        return [
            'email' => self::faker()->unique()->safeEmail(),
            'password' => self::DEFAULT_PASSWORD,
        ];
    }

    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $user->setPassword($this->passwordHasher->hashPassword($user, (string) $user->getPassword()));
        });
    }
}
