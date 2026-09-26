<?php

namespace App\Tests\Application\Project;

use App\Identity\Event\GuestAccountsPurged;
use App\Project\EventListener\DeleteStorageOnGuestAccountsPurged;
use App\Project\Service\ProjectImageStorage;
use App\Tests\Application\ApiTestCase;
use League\Flysystem\UnableToDeleteDirectory;
use Psr\Log\AbstractLogger;
use Symfony\Component\Uid\Uuid;

final class DeleteStorageOnGuestAccountsPurgedTest extends ApiTestCase
{
    public function testAFailedDeletionIsLoggedAndTheRestStillRun(): void
    {
        $failing = Uuid::v7();
        $succeeding = Uuid::v7();

        $storage = new class($failing) extends ProjectImageStorage {
            /** @var list<string> */
            public array $removed = [];

            public function __construct(
                private readonly Uuid $failFor,
            ) {
            }

            public function removeForOwner(Uuid $ownerId): void
            {
                if ($ownerId->equals($this->failFor)) {
                    throw UnableToDeleteDirectory::atLocation('prefix', 'disk full');
                }

                $this->removed[] = (string) $ownerId;
            }
        };

        $logger = new class extends AbstractLogger {
            /** @var list<string> */
            public array $errors = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ('error' === $level) {
                    $this->errors[] = (string) $message;
                }
            }
        };

        new DeleteStorageOnGuestAccountsPurged($storage, $logger)(new GuestAccountsPurged([$failing, $succeeding]));

        self::assertCount(1, $logger->errors, 'The failed deletion must leave a record.');
        self::assertSame([(string) $succeeding], $storage->removed, 'One failure must not stop the others.');
    }
}
