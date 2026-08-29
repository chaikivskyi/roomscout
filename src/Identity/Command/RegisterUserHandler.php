<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\User;
use App\Identity\Exception\EmailAlreadyRegistered;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class RegisterUserHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $user = new User($command->userId);
        $user->setEmail($command->email);

        if ([] !== $command->roles) {
            $user->setRoles($command->roles);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $command->plainPassword));

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException $e) {
            throw new EmailAlreadyRegistered($command->email, $e);
        }
    }
}
