<?php

namespace App\Tests\Application\Identity;

use App\Api\Bus\CommandBusInterface;
use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Command\PurgeGuests;
use App\Identity\Entity\User;
use App\Identity\Repository\UserRepository;
use App\Project\Service\ProjectImageStorage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use App\Tests\Factory\ProjectImageVersionFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Assert;
use Symfony\Component\Uid\Uuid;

final class PurgeGuestsTest extends ApiTestCase
{
    protected function tearDown(): void
    {
        $storage = $this->storage();
        foreach ($storage->listContents('')->toArray() as $item) {
            $item->isDir() ? $storage->deleteDirectory($item->path()) : $storage->delete($item->path());
        }

        parent::tearDown();
    }

    public function testAnEmptyGuestOlderThanTheWindowIsPurged(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-2 days'),
            'lastActiveAt' => new \DateTimeImmutable('-2 days'),
        ]);

        $this->purge();

        self::assertNull($this->entityManager()->find(User::class, $guest->getId()));
    }

    public function testAFreshEmptyGuestIsKept(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-10 minutes'),
            'lastActiveAt' => new \DateTimeImmutable('-10 minutes'),
        ]);

        $this->purge();

        self::assertNotNull($this->entityManager()->find(User::class, $guest->getId()));
    }

    public function testAGuestSeenRecentlyIsKeptEvenWhenItsProjectIsOld(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-90 days'),
            'lastActiveAt' => new \DateTimeImmutable('-1 hour'),
        ]);
        ProjectFactory::createOne([
            'user' => $guest,
            'createdAt' => new \DateTimeImmutable('-90 days'),
            'updatedAt' => new \DateTimeImmutable('-90 days'),
        ]);

        $this->purge();

        self::assertNotNull($this->entityManager()->find(User::class, $guest->getId()));
    }

    public function testAGuestThatNeverReturnedAfterMintingIsPurgedOnTheShortWindow(): void
    {
        $mintedAt = new \DateTimeImmutable('-2 days');
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => $mintedAt,
            'lastActiveAt' => $mintedAt,
        ]);

        $this->purge();

        self::assertNull($this->entityManager()->find(User::class, $guest->getId()));
    }

    public function testAGuestStillWithinTheLongWindowIsKeptEvenAfterTheShortOne(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-20 days'),
            'lastActiveAt' => new \DateTimeImmutable('-5 days'),
        ]);

        $this->purge();

        self::assertNotNull($this->entityManager()->find(User::class, $guest->getId()));
    }

    public function testAGuestWithNoRecentActivityAnywhereIsPurgedWithItsImageFiles(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-90 days'),
            'lastActiveAt' => new \DateTimeImmutable('-90 days'),
        ]);
        $project = ProjectFactory::createOne([
            'user' => $guest,
            'createdAt' => new \DateTimeImmutable('-60 days'),
            'updatedAt' => new \DateTimeImmutable('-60 days'),
        ]);
        $stalePath = sprintf('%s/version/image.png', ProjectImageStorage::prefixFor($guest->getId()));
        ProjectImageVersionFactory::createOne([
            'project' => $project,
            'imagePath' => $stalePath,
            'createdAt' => new \DateTimeImmutable('-60 days'),
        ]);

        $this->storage()->write($stalePath, 'bytes');

        $this->purge();

        self::assertNull($this->entityManager()->find(User::class, $guest->getId()));
        self::assertFalse($this->storage()->fileExists($stalePath));
    }

    public function testAGuestClaimedBetweenSelectionAndDeletionKeepsBothItsRowAndItsFiles(): void
    {
        $guest = UserFactory::new()->guest()->create([
            'createdAt' => new \DateTimeImmutable('-90 days'),
            'lastActiveAt' => new \DateTimeImmutable('-90 days'),
        ]);
        $project = ProjectFactory::createOne([
            'user' => $guest,
            'createdAt' => new \DateTimeImmutable('-60 days'),
            'updatedAt' => new \DateTimeImmutable('-60 days'),
        ]);
        $claimedPath = sprintf('%s/version/image.png', ProjectImageStorage::prefixFor($guest->getId()));
        ProjectImageVersionFactory::createOne([
            'project' => $project,
            'imagePath' => $claimedPath,
            'createdAt' => new \DateTimeImmutable('-60 days'),
        ]);
        $this->storage()->write($claimedPath, 'bytes');

        $guestId = $guest->getId();

        $registry = static::getContainer()->get(ManagerRegistry::class);
        $realUsers = new UserRepository($registry);

        $racingUsers = new class($realUsers, $this->entityManager(), $guestId) implements UserRepositoryInterface {
            public function __construct(
                private readonly UserRepositoryInterface $inner,
                private readonly EntityManagerInterface $entityManager,
                private readonly Uuid $racingUserId,
            ) {
            }

            public function findOneById(Uuid $id): ?User
            {
                return $this->inner->findOneById($id);
            }

            public function findOneByEmail(string $email): ?User
            {
                return $this->inner->findOneByEmail($email);
            }

            public function save(User $user): void
            {
                $this->inner->save($user);
            }

            public function updateLastActiveAt(Uuid $id, \DateTimeImmutable $at): void
            {
                $this->inner->updateLastActiveAt($id, $at);
            }

            public function findStaleGuestIds(\DateTimeImmutable $idleBefore, \DateTimeImmutable $staleBefore, int $limit): array
            {
                return $this->inner->findStaleGuestIds($idleBefore, $staleBefore, $limit);
            }

            public function deleteByIds(array $ids): array
            {
                $racer = $this->entityManager->find(User::class, $this->racingUserId);
                Assert::assertNotNull($racer);
                $racer->setEmail('claimed@example.com');
                $this->entityManager->flush();

                return $this->inner->deleteByIds($ids);
            }
        };

        static::getContainer()->set(UserRepositoryInterface::class, $racingUsers);

        $this->purge();

        $survivor = $this->entityManager()->find(User::class, $guestId);
        self::assertNotNull($survivor, 'A guest claimed mid-purge must keep its row.');
        self::assertSame('claimed@example.com', $survivor->getEmail());
        self::assertTrue(
            $this->storage()->fileExists($claimedPath),
            'A guest claimed mid-purge must keep its files.',
        );
    }

    public function testRegisteredUsersAreNeverTouched(): void
    {
        $user = UserFactory::createOne(['createdAt' => new \DateTimeImmutable('-400 days')]);

        $this->purge();

        self::assertNotNull($this->entityManager()->find(User::class, $user->getId()));
    }

    public function testOneRunDrainsMoreThanASingleBatch(): void
    {
        $mintedAt = new \DateTimeImmutable('-40 days');

        for ($i = 0; $i < 5; ++$i) {
            UserFactory::new()->guest()->create(['createdAt' => $mintedAt, 'lastActiveAt' => $mintedAt]);
        }

        $command = new PurgeGuests(emptyTtlHours: 24, staleTtlDays: 30, batch: 2, maxBatches: 20);
        static::getContainer()->get(CommandBusInterface::class)->dispatch($command);
        $this->entityManager()->clear();

        self::assertSame(0, UserFactory::count(), 'A run must keep going until the backlog is drained.');
    }

    public function testTheBatchCapBoundsASingleRun(): void
    {
        $mintedAt = new \DateTimeImmutable('-40 days');

        for ($i = 0; $i < 5; ++$i) {
            UserFactory::new()->guest()->create(['createdAt' => $mintedAt, 'lastActiveAt' => $mintedAt]);
        }

        $command = new PurgeGuests(emptyTtlHours: 24, staleTtlDays: 30, batch: 1, maxBatches: 2);
        static::getContainer()->get(CommandBusInterface::class)->dispatch($command);
        $this->entityManager()->clear();

        self::assertSame(3, UserFactory::count());
    }

    public function testBatchesCompletedBeforeAListenerThrowsStayPurged(): void
    {
        $mintedAt = new \DateTimeImmutable('-40 days');

        for ($i = 0; $i < 5; ++$i) {
            UserFactory::new()->guest()->create(['createdAt' => $mintedAt, 'lastActiveAt' => $mintedAt]);
        }

        $storageOperator = static::getContainer()->get('project.storage');
        self::assertInstanceOf(FilesystemOperator::class, $storageOperator);

        $throwingImageStorage = new class($storageOperator) extends ProjectImageStorage {
            public int $calls = 0;

            public function removeForOwner(Uuid $ownerId): void
            {
                if (3 === ++$this->calls) {
                    throw new \RuntimeException('Disk exploded.');
                }

                parent::removeForOwner($ownerId);
            }
        };

        static::getContainer()->set(ProjectImageStorage::class, $throwingImageStorage);

        $command = new PurgeGuests(emptyTtlHours: 24, staleTtlDays: 30, batch: 1, maxBatches: 20);

        try {
            static::getContainer()->get(CommandBusInterface::class)->dispatch($command);
            self::fail('Expected the listener exception to propagate out of the purge.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('Disk exploded.', $e->getMessage());
        }

        $this->entityManager()->clear();

        self::assertSame(2, UserFactory::count(), 'Guests deleted before the throw must stay deleted.');
    }

    private function purge(): void
    {
        static::getContainer()->get(CommandBusInterface::class)->dispatch(new PurgeGuests(24, 30, 500));
        $this->entityManager()->clear();
    }

    private function storage(): FilesystemOperator
    {
        return static::getContainer()->get('project.storage');
    }
}
