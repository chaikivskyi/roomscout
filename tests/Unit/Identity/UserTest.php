<?php

namespace App\Tests\Unit\Identity;

use App\Identity\Entity\User;
use App\Identity\Enum\Role;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class UserTest extends TestCase
{
    public function testAUserWithoutAnEmailIsAGuest(): void
    {
        $user = new User();

        self::assertTrue($user->isGuest());
        self::assertSame([Role::Guest->value], $user->getRoles());
    }

    public function testAGuestIdentifiesByUuid(): void
    {
        $id = Uuid::v7();

        self::assertSame((string) $id, new User($id)->getUserIdentifier());
    }

    public function testAUserWithAnEmailIsNotAGuest(): void
    {
        $user = new User()->setEmail('someone@example.com');

        self::assertFalse($user->isGuest());
        self::assertSame([Role::User->value], $user->getRoles());
        self::assertSame('someone@example.com', $user->getUserIdentifier());
    }

    public function testAnAdminKeepsBothRoles(): void
    {
        $user = new User()->setEmail('admin@example.com')->addRole(Role::Admin);

        self::assertSame([Role::Admin->value, Role::User->value], $user->getRoles());
    }

    public function testCreatedAtIsSetOnConstruction(): void
    {
        self::assertEqualsWithDelta(
            new \DateTimeImmutable()->getTimestamp(),
            new User()->getCreatedAt()->getTimestamp(),
            5,
        );
    }
}
