<?php

namespace App\CatalogSearch\EventListener;

use App\CatalogSearch\Command\MatchContextProducts;
use App\Project\Entity\ProjectContext;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkContextFailedOnMatchingFailure
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

        if (!$message instanceof MatchContextProducts) {
            return;
        }

        if (!Uuid::isValid($message->contextId)) {
            return;
        }

        if (!$this->entityManager->isOpen()) {
            $this->registry->resetManager();
        }

        $context = $this->entityManager->find(ProjectContext::class, Uuid::fromString($message->contextId));

        if (null === $context) {
            return;
        }

        if (!$context->markFailed()) {
            return;
        }

        $this->entityManager->flush();

        $this->logger->error('Matching failed permanently; context marked failed.', ['contextId' => $message->contextId]);
    }
}
