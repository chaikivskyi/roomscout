<?php

namespace App\Api\Messenger;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

final class FinalFailureMarker
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param class-string<FailableInterface> $entityClass
     * @param array<string, string>           $logContext
     */
    public function markFailed(
        WorkerMessageFailedEvent $event,
        string $entityClass,
        string $entityId,
        string $logMessage,
        array $logContext = [],
    ): void {
        if ($event->willRetry()) {
            return;
        }

        if (!Uuid::isValid($entityId)) {
            return;
        }

        try {
            if (!$this->entityManager->isOpen()) {
                $this->registry->resetManager();
            }

            $entity = $this->entityManager->find($entityClass, Uuid::fromString($entityId));

            if (null === $entity) {
                return;
            }

            if (!$entity->markFailed()) {
                return;
            }

            $this->entityManager->flush();

            $this->logger->error($logMessage, $logContext);
        } catch (\Throwable $exception) {
            $this->logger->critical('Failed to mark entity as failed after exhausted retries.', [
                'entity_class' => $entityClass,
                'entity_id' => $entityId,
                'exception' => $exception,
            ]);
        }
    }
}
