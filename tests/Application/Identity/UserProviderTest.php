<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class UserProviderTest extends ApiTestCase
{
    public function testARegisteredUserLoadsByEmail(): void
    {
        UserFactory::createOne(['email' => 'known@example.com']);

        $loaded = $this->users()->loadUserByIdentifier('known@example.com');

        self::assertInstanceOf(User::class, $loaded);
        self::assertSame('known@example.com', $loaded->getEmail());
    }

    public function testAGuestLoadsByUuid(): void
    {
        $guest = UserFactory::new()->guest()->create();

        $loaded = $this->users()->loadUserByIdentifier((string) $guest->getId());

        self::assertInstanceOf(User::class, $loaded);
        self::assertTrue($loaded->isGuest());
        self::assertTrue($loaded->getId()->equals($guest->getId()));
    }

    public function testAnUnknownIdentifierLoadsNothing(): void
    {
        self::assertNull($this->users()->loadUserByIdentifier('nobody@example.com'));
    }

    public function testAGuestTokenAuthenticates(): void
    {
        $guest = UserFactory::new()->guest()->create();

        $this->authClient($this->tokenFor($guest))->request('GET', '/api/projects');

        self::assertResponseIsSuccessful();
    }

    private function users(): UserRepository
    {
        return static::getContainer()->get(UserRepository::class);
    }
}
