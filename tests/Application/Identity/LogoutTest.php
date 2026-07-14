<?php

namespace App\Tests\Application\Identity;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class LogoutTest extends ApiTestCase
{
    public function testLogoutBlocklistsTokenAndReturns204(): void
    {
        $user = UserFactory::createOne();
        $token = $this->tokenFor($user);
        $client = $this->authClient($token);

        $client->request('GET', '/api/users/'.$user->getId());
        self::assertResponseIsSuccessful();

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $client->request('GET', '/api/users/'.$user->getId());
        self::assertResponseStatusCodeSame(401);
    }

    public function testLogoutIsIdempotentWithoutToken(): void
    {
        static::createClient()->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);
    }

    public function testLogoutWithRevokedTokenReturns401(): void
    {
        $client = $this->authClient($this->tokenFor(UserFactory::createOne()));

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(204);

        $client->request('POST', '/api/logout');
        self::assertResponseStatusCodeSame(401);
    }
}
