<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Exception\EmailAlreadyRegistered;
use App\Identity\Exception\UserNotFound;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ClaimGuestAccountHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function __invoke(ClaimGuestAccount $command): void
    {
        $user = $this->users->findOneById($command->guestId) ?? throw UserNotFound::byId($command->guestId);

        if (!$user->isGuest()) {
            throw new \LogicException('Only a guest account can be claimed.');
        }

        $user->setEmail($command->email);
        $user->setPassword($this->passwordHasher->hashPassword($user, $command->plainPassword));

        try {
            $this->users->save($user);
        } catch (UniqueConstraintViolationException $e) {
            throw new EmailAlreadyRegistered($command->email, $e);
        }
    }
}
