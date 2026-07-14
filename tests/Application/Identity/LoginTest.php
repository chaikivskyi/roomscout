<?php

namespace App\Tests\Application\Identity;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class LoginTest extends ApiTestCase
{
    public function testLoginWithValidCredentialsReturnsUsableToken(): void
    {
        $user = UserFactory::createOne(['email' => 'login@example.com']);

        $response = static::createClient()->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'login@example.com', 'password' => UserFactory::DEFAULT_PASSWORD],
        ]);

        self::assertResponseIsSuccessful();
        $token = $response->toArray()['token'];
        self::assertNotEmpty($token);

        $this->authClient($token)->request('GET', '/api/users/'.$user->getId());
        self::assertResponseIsSuccessful();
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        UserFactory::createOne(['email' => 'login@example.com']);

        static::createClient()->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'login@example.com', 'password' => 'wrong-password'],
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testLoginWithUnknownEmailReturns401(): void
    {
        static::createClient()->request('POST', '/api/login', [
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['email' => 'ghost@example.com', 'password' => UserFactory::DEFAULT_PASSWORD],
        ]);

        self::assertResponseStatusCodeSame(401);
    }
}
