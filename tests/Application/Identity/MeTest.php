<?php

namespace App\Tests\Application\Identity;

use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class MeTest extends ApiTestCase
{
    public function testMeReturnsIdEmailAndGuestFlag(): void
    {
        $user = UserFactory::createOne(['email' => 'me@example.com']);

        $response = $this->authClient($this->tokenFor($user))
            ->request('GET', '/api/me');

        self::assertResponseIsSuccessful();
        self::assertJsonContains([
            'id' => $user->getId()->toRfc4122(),
            'email' => 'me@example.com',
            'guest' => false,
        ]);

        $data = $response->toArray();
        self::assertArrayNotHasKey('password', $data);
        self::assertArrayNotHasKey('roles', $data);
        self::assertArrayNotHasKey('totpSecret', $data);
    }

    public function testAGuestSeesItselfAsAGuest(): void
    {
        $guest = UserFactory::new()->guest()->create();

        $response = $this->authClient($this->tokenFor($guest))
            ->request('GET', '/api/me');

        self::assertResponseIsSuccessful();

        $data = $response->toArray(false);
        self::assertSame($guest->getId()->toRfc4122(), $data['id']);
        self::assertNull($data['email']);
        self::assertTrue($data['guest']);
    }

    public function testAnonymousMeRequestReturns401(): void
    {
        static::createClient()->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }
}
