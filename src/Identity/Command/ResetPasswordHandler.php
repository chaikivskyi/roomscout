<?php

namespace App\Identity\Command;

use App\Identity\Api\PasswordResetTokenRepositoryInterface;
use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Exception\InvalidPasswordResetToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class ResetPasswordHandler
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $resetTokens,
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        $token = $this->resetTokens->findOneByTokenHash(hash('sha256', $command->token));

        if (null === $token || $token->isExpired(new \DateTimeImmutable())) {
            throw new InvalidPasswordResetToken();
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $command->plainPassword));

        $this->entityManager->wrapInTransaction(function () use ($user, $token): void {
            $this->users->save($user);
            $this->resetTokens->delete($token);
        });
    }
}
