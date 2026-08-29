<?php

namespace App\Identity\Command;

use App\Identity\Api\PasswordResetTokenRepositoryInterface;
use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\PasswordResetToken;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RequestPasswordResetHandler
{
    private const string TOKEN_TTL = '+1 hour';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordResetTokenRepositoryInterface $resetTokens,
        private readonly MailerInterface $mailer,
        private readonly EntityManagerInterface $entityManager,
        #[Autowire(env: 'IDENTITY_RESET_URL')]
        private readonly string $resetUrl,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        $user = $this->users->findOneByEmail($command->email);

        if (null === $user) {
            return;
        }

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new \DateTimeImmutable(self::TOKEN_TTL);

        $this->entityManager->wrapInTransaction(function () use ($user, $plainToken, $expiresAt): void {
            $this->resetTokens->deleteForUser($user);
            $this->resetTokens->save(new PasswordResetToken($user, hash('sha256', $plainToken), $expiresAt));
        });

        $this->mailer->send(
            new TemplatedEmail()
                ->to($command->email)
                ->subject('Reset your password')
                ->htmlTemplate('identity/reset_password_email.html.twig')
                ->context([
                    'resetUrl' => $this->resetUrl.'?token='.$plainToken,
                    'expiresAt' => $expiresAt,
                ]),
        );
    }
}
