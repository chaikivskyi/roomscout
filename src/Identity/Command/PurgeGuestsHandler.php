<?php

namespace App\Identity\Command;

use App\Identity\Api\UserRepositoryInterface;
use App\Identity\Event\GuestAccountsPurged;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class PurgeGuestsHandler
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly EventDispatcherInterface $events,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(PurgeGuests $command): void
    {
        $now = new \DateTimeImmutable();
        $idleBefore = $now->modify(sprintf('-%d hours', $command->emptyTtlHours));
        $staleBefore = $now->modify(sprintf('-%d days', $command->staleTtlDays));

        $batches = 0;
        $purged = 0;

        try {
            while ($batches < $command->maxBatches) {
                $doomed = $this->users->findStaleGuestIds($idleBefore, $staleBefore, $command->batch);

                if ([] === $doomed) {
                    break;
                }

                ++$batches;

                $deleted = $this->users->deleteByIds($doomed);

                if ([] === $deleted) {
                    break;
                }

                $purged += \count($deleted);

                $this->events->dispatch(new GuestAccountsPurged($deleted));
            }
        } finally {
            $this->logger->info('Guest purge finished.', [
                'batches' => $batches,
                'purged' => $purged,
                'reachedCap' => $batches >= $command->maxBatches,
            ]);
        }
    }
}
