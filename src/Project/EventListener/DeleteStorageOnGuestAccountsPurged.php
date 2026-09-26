<?php

namespace App\Project\EventListener;

use App\Identity\Event\GuestAccountsPurged;
use App\Project\Service\ProjectImageStorage;
use League\Flysystem\FilesystemException;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

#[AsEventListener]
final class DeleteStorageOnGuestAccountsPurged
{
    public function __construct(
        private readonly ProjectImageStorage $imageStorage,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(GuestAccountsPurged $event): void
    {
        foreach ($event->userIds as $userId) {
            try {
                $this->imageStorage->removeForOwner($userId);
            } catch (FilesystemException $e) {
                $this->logger->error('Could not delete a purged guest\'s stored images; the files are now orphaned.', [
                    'user' => (string) $userId,
                    'prefix' => ProjectImageStorage::prefixFor($userId),
                    'exception' => $e,
                ]);
            }
        }
    }
}
