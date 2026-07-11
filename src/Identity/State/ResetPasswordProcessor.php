<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\ApiResource\ResetPasswordRequest;
use App\Identity\Api\PasswordResetTokenRepositoryInterface;
use App\Identity\Api\UserRepositoryInterface;
use DateTimeImmutable;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * @implements ProcessorInterface<ResetPasswordRequest, void>
 */
final class ResetPasswordProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly PasswordResetTokenRepositoryInterface $resetTokens,
        private readonly UserRepositoryInterface $users,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $token = $this->resetTokens->findOneByTokenHash(hash('sha256', $data->token));

        if (null === $token || $token->isExpired(new DateTimeImmutable())) {
            throw new UnprocessableEntityHttpException('Invalid or expired token.');
        }

        $user = $token->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $data->password));

        $this->users->save($user);
        $this->resetTokens->delete($token);
    }
}
