<?php

namespace App\Tests\Application\Identity;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class MeTest extends ApiTestCase
{
    public function testMeReturnsOnlyIdAndEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'me@example.com']);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['id' => $user->getId()->toRfc4122(), 'email' => 'me@example.com']);

        $data = $response->toArray();
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('roles', $data);
        self::assertArrayNotHasKey('totpSecret', $data);
    }

    public function testAnonymousMeRequestReturns401(): void
    {
        static::createClient()->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
