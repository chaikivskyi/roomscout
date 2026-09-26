<?php

namespace App\Tests\Application\Identity;

use App\Identity\Entity\User;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\UserFactory;

final class LastActiveAtTest extends ApiTestCase
{
    public function testAnAuthenticatedRequestRefreshesAStaleLastActiveAt(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-40 days'),
            'lastActiveAt' => new \DateTimeImmutable('-40 days'),
        ]);

        $this->authClient($this->tokenFor($guest))->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(User::class, $guest->getId());

        self::assertNotNull($reloaded);
        self::assertGreaterThan(
            new \DateTimeImmutable('-1 hour'),
            $reloaded->getLastActiveAt(),
            'An authenticated request must keep the account out of the purge window.',
        );
    }

    public function testAGuestsFirstSignOfLifeIsRecordedEvenWellWithinTheThrottleWindow(): void
    {
        $mintedAt = new \DateTimeImmutable('-1 minute');
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => $mintedAt,
            'lastActiveAt' => $mintedAt,
        ]);

        $this->authClient($this->tokenFor($guest))->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(User::class, $guest->getId());

        self::assertNotNull($reloaded);
        self::assertGreaterThan(
            $mintedAt,
            $reloaded->getLastActiveAt(),
            'A guest whose lastActiveAt still equals createdAt must have its first request recorded '
            .'immediately, not held back by the throttle, or it can be purged after 24 hours despite '
            .'having just been active.',
        );
    }

    public function testARecentlySeenUserIsNotWrittenOnEveryRequest(): void
    {
        $seenAt = new \DateTimeImmutable('-1 minute');
        $user = UserFactory::createOne([
            'createdAt' => new \DateTimeImmutable('-10 days'),
            'lastActiveAt' => $seenAt,
        ]);

        $this->authClient($this->tokenFor($user))->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $this->entityManager()->clear();
        $reloaded = $this->entityManager()->find(User::class, $user->getId());

        self::assertNotNull($reloaded);
        self::assertSame(
            $seenAt->getTimestamp(),
            $reloaded->getLastActiveAt()->getTimestamp(),
            'Within the throttle window the row must not be written again.',
        );
    }

    public function testAnAnonymousRequestWritesNothing(): void
    {
        static::createClient()->request('GET', '/api/me');

        self::assertResponseStatusCodeSame(401);
    }

    public function testAnUnrelatedPendingChangeIsNotWrittenByTheActivityUpdate(): void
    {
        $user = UserFactory::createOne([
            'email' => 'stable@example.com',
            'createdAt' => new \DateTimeImmutable('-40 days'),
            'lastActiveAt' => new \DateTimeImmutable('-40 days'),
        ]);

        $token = $this->tokenFor($user);
        $client = $this->authClient($token);

        $entityManager = $this->entityManager();
        $managed = $entityManager->find(User::class, $user->getId());
        self::assertNotNull($managed);
        $managed->setEmail('dirty@example.com');

        $client->request('GET', '/api/me');
        self::assertResponseIsSuccessful();

        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $user->getId());

        self::assertNotNull($reloaded);
        self::assertSame(
            'stable@example.com',
            $reloaded->getEmail(),
            'The activity update must not commit unrelated pending changes.',
        );
        self::assertGreaterThan(new \DateTimeImmutable('-1 hour'), $reloaded->getLastActiveAt());
    }
}
