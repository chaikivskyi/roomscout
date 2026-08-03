<?php

namespace App\Placement\EventListener;

use App\Placement\Entity\ProductPlacement;
use App\Placement\Message\GeneratePlacementImageMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkPlacementFailedOnGenerationFailure
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ManagerRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        if ($event->willRetry()) {
            return;
        }

        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof GeneratePlacementImageMessage) {
            return;
        }

        if (!Uuid::isValid($message->placementId)) {
            return;
        }

        if (!$this->entityManager->isOpen()) {
            $this->registry->resetManager();
        }

        $placement = $this->entityManager->find(ProductPlacement::class, Uuid::fromString($message->placementId));

        if (null === $placement) {
            return;
        }

        if (!$placement->markFailed()) {
            return;
        }

        $this->entityManager->flush();

        $this->logger->error('Placement generation failed permanently; placement marked failed.', ['placementId' => $message->placementId]);
    }
}
