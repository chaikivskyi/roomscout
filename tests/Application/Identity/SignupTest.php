<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;
use Symfony\Component\Uid\Uuid;

final class SignupTest extends ApiTestCase
{
    public function testSignupCreatesUserAndReturnsWorkingJwt(): void
    {
        $client = static::createClient();

        $response = $client->request('POST', '/api/signup', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'new@example.com', 'password' => 'Password123'],
        ]);

        self::assertResponseStatusCodeSame(201);
        self::assertJsonContains(['email' => 'new@example.com']);

        $data = $response->toArray();
        self::assertIsString($data['id']);
        self::assertTrue(Uuid::isValid($data['id']));
        self::assertIsString($data['token']);
        self::assertNotEmpty($data['token']);
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('roles', $data);

        $user = $this->entityManager()->getRepository(User::class)->findOneBy(['email' => 'new@example.com']);
        self::assertNotNull($user);
        self::assertNotSame('Password123', $user->getPassword());
        self::assertSame($user->getId()->toRfc4122(), $data['id']);

        $this->authClient($data['token'])->request('GET', '/api/me');
        self::assertResponseIsSuccessful();
    }

    public function testSignupWithDuplicateEmailReturns422(): void
    {
        UserFactory::createOne(['email' => 'taken@example.com']);

        static::createClient()->request('POST', '/api/signup', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'taken@example.com', 'password' => 'Password123'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains([
            'violations' => [['propertyPath' => 'email', 'message' => 'An account with this email already exists.']],
        ]);
    }

    public function testSignupWithInvalidEmailReturns422(): void
    {
        static::createClient()->request('POST', '/api/signup', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'not-an-email', 'password' => 'Password123'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'email']]]);
    }

    public function testSignupWithTooShortPasswordReturns422(): void
    {
        static::createClient()->request('POST', '/api/signup', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'short@example.com', 'password' => '1234567'],
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertJsonContains(['violations' => [['propertyPath' => 'password']]]);
    }

    public function testSignupWithBlankBodyReturns422WithBothViolations(): void
    {
        $response = static::createClient()->request('POST', '/api/signup', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [],
        ]);

        self::assertResponseStatusCodeSame(422);

        /** @var array{violations: list<array{propertyPath: string}>} $data */
        $data = $response->toArray(false);
        $paths = array_column($data['violations'], 'propertyPath');
        self::assertContains('email', $paths);
        self::assertContains('password', $paths);
    }
}
