<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\PasswordResetToken;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\PasswordResetTokenFactory;
use App\Tests\Factory\UserFactory;

final class ResetPasswordTest extends ApiTestCase
{
    use InteractsWithPasswordReset;

    public function testFullResetFlowChangesPasswordAndConsumesToken(): void
    {
        $user = UserFactory::createOne(['email' => 'reset@example.com', 'password' => 'OldPassword1']);
        $client = static::createClient();

        $plainToken = $this->requestPasswordResetToken($client, 'reset@example.com');

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => $plainToken, 'password' => 'NewPassword1'],
        ]);
        self::assertResponseStatusCodeSame(204);

        $client->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'reset@example.com', 'password' => 'OldPassword1'],
        ]);
        self::assertResponseStatusCodeSame(401);

        $client->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'reset@example.com', 'password' => 'NewPassword1'],
        ]);
        self::assertResponseIsSuccessful();

        self::assertSame([], $this->entityManager()->getRepository(PasswordResetToken::class)->findBy(['user' => $user]));

        $client->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => $plainToken, 'password' => 'AnotherPassword1'],
        ]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testGarbageTokenReturns422(): void
    {
        static::createClient()->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => str_repeat('ab', 32), 'password' => 'NewPassword1'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Invalid or expired token.']);
    }

    public function testExpiredTokenReturns422(): void
    {
        $plainToken = bin2hex(random_bytes(32));

        PasswordResetTokenFactory::createOne([
            'user' => UserFactory::createOne(['email' => 'expired@example.com']),
            'tokenHash' => hash('sha256', $plainToken),
            'expiresAt' => new \DateTimeImmutable('-1 minute'),
        ]);

        static::createClient()->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => $plainToken, 'password' => 'NewPassword1'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['detail' => 'Invalid or expired token.']);
    }

    public function testTooShortNewPasswordReturns422(): void
    {
        static::createClient()->request('POST', '/api/reset-password', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['token' => str_repeat('ab', 32), 'password' => 'short'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'password']]]);
    }
}
