<?php

namespace App\Tests\Application\Identity;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class UserProfileTest extends ApiTestCase
{
    public function testOwnProfileReturnsOnlyIdAndEmail(): void
    {
        $user = UserFactory::createOne(['email' => 'me@example.com']);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/users/'.$user->getId());

        self::assertResponseIsSuccessful();
        self::assertJsonContains(['id' => $user->getId()->toRfc4122(), 'email' => 'me@example.com']);

        $data = $response->toArray();
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('roles', $data);
        self::assertArrayNotHasKey('totpSecret', $data);
    }

    public function testOtherUsersProfileIsForbidden(): void
    {
        $alice = UserFactory::createOne(['email' => 'alice@example.com']);
        $bob = UserFactory::createOne(['email' => 'bob@example.com']);

        $this->authClient($this->tokenFor($alice))
            ->request('GET', '/api/users/'.$bob->getId());

        self::assertResponseStatusCodeSame(403);
    }

    public function testAnonymousProfileRequestReturns401(): void
    {
        $user = UserFactory::createOne();

        static::createClient()->request('GET', '/api/users/'.$user->getId());

        self::assertResponseStatusCodeSame(401);
    }

    public function testUnknownUserIdReturns404ForAuthenticatedUser(): void
    {
        $user = UserFactory::createOne();

        $this->authClient($this->tokenFor($user))->request('GET', '/api/users/999999');

        self::assertResponseStatusCodeSame(404);
    }
}
