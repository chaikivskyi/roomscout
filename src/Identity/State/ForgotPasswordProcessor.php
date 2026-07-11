<?php

namespace App\Identity\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\ApiResource\ForgotPasswordRequest;
use App\Identity\Api\PasswordResetTokenRepositoryInterface;
use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Entity\PasswordResetToken;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;

/**
 * @implements ProcessorInterface<ForgotPasswordRequest, void>
 */
final class ForgotPasswordProcessor implements ProcessorInterface
{
    private const string TOKEN_TTL = '+1 hour';

    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly PasswordResetTokenRepositoryInterface $resetTokens,
        private readonly MailerInterface $mailer,
        #[Autowire(env: 'IDENTITY_RESET_URL')]
        private readonly string $resetUrl,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): void
    {
        $user = $this->users->findOneByEmail($data->email);

        if (null === $user) {
            return;
        }

        $this->resetTokens->deleteForUser($user);

        $plainToken = bin2hex(random_bytes(32));
        $expiresAt = new DateTimeImmutable(self::TOKEN_TTL);

        $this->resetTokens->save(new PasswordResetToken($user, hash('sha256', $plainToken), $expiresAt));

        $this->mailer->send(
            new TemplatedEmail()
                ->to($user->getEmail())
                ->subject('Reset your password')
                ->htmlTemplate('identity/reset_password_email.html.twig')
                ->context([
                    'resetUrl' => $this->resetUrl.'?token='.$plainToken,
                    'expiresAt' => $expiresAt,
                ]),
        );
    }
}
