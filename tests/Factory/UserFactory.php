<?php

namespace App\Tests\Factory;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Zenstruck\Foundry\Object\Instantiator;
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

    public function admin(): static
    {
        return $this->with(['roles' => [Role::Admin->value]]);
    }

    public function guest(): static
    {
        return $this->with(['email' => null, 'password' => null]);
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
        return $this
            ->instantiateWith(Instantiator::withConstructor()->alwaysForce('createdAt', 'lastActiveAt'))
            ->afterInstantiate(function (User $user): void {
                $plain = $user->getPassword();

                if (null === $plain) {
                    return;
                }

                $user->setPassword($this->passwordHasher->hashPassword($user, $plain));
            });
    }
}
