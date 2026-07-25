<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\PasswordResetToken;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Mime\Email;

final class ForgotPasswordTest extends ApiTestCase
{
    use InteractsWithPasswordReset;

    public function testKnownEmailQueuesResetEmailAndStoresHashedToken(): void
    {
        $user = UserFactory::createOne(['email' => 'forgetful@example.com']);
        $client = static::createClient();

        $plainToken = $this->requestPasswordResetToken($client, 'forgetful@example.com');

        $mail = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $mail);
        self::assertSame('forgetful@example.com', $mail->getTo()[0]->getAddress());
        self::assertSame('Reset your password', $mail->getSubject());

        $tokens = $this->entityManager()->getRepository(PasswordResetToken::class)->findBy(['user' => $user]);
        self::assertCount(1, $tokens);
        self::assertSame(hash('sha256', $plainToken), $tokens[0]->getTokenHash());
        self::assertNotSame($plainToken, $tokens[0]->getTokenHash());

        $expiresIn = $tokens[0]->getExpiresAt()->getTimestamp() - time();
        self::assertGreaterThan(3500, $expiresIn);
        self::assertLessThanOrEqual(3600, $expiresIn);
    }

    public function testUnknownEmailReturns202AndSendsNothing(): void
    {
        static::createClient()->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'ghost@example.com'],
        ]);

        self::assertResponseStatusCodeSame(202);
        self::assertQueuedEmailCount(0);
        self::assertSame([], $this->entityManager()->getRepository(PasswordResetToken::class)->findAll());
    }

    public function testNewRequestReplacesPreviousToken(): void
    {
        $user = UserFactory::createOne(['email' => 'repeat@example.com']);
        $client = static::createClient();

        $firstToken = $this->requestPasswordResetToken($client, 'repeat@example.com');
        $secondToken = $this->requestPasswordResetToken($client, 'repeat@example.com');

        self::assertNotSame($firstToken, $secondToken);
        self::assertCount(1, $this->entityManager()->getRepository(PasswordResetToken::class)->findBy(['user' => $user]));

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => $firstToken, 'password' => 'NewPassword1'],
        ]);
        self::assertResponseStatusCodeSame(422);

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => $secondToken, 'password' => 'NewPassword1'],
        ]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testInvalidEmailFormatReturns422(): void
    {
        static::createClient()->request('POST', '/api/forgot-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'not-an-email'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'email']]]);
    }
}
