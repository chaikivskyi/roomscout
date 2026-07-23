<?php

namespace App\CatalogSearch\EventListener;

use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\Project\Entity\Project;
use App\Project\Enum\ProjectStatus;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkProjectFailedOnMatchingFailure
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

        if (!$message instanceof MatchProjectProductsMessage) {
            return;
        }

        if (!$this->entityManager->isOpen()) {
            $this->registry->resetManager();
        }

        $project = $this->entityManager->find(Project::class, Uuid::fromString($message->projectId));

        if (null === $project || ProjectStatus::Processing !== $project->getStatus()) {
            return;
        }

        $project->markFailed();
        $this->entityManager->flush();

        $this->logger->error('Matching failed permanently; project marked failed.', ['projectId' => $message->projectId]);
    }
}
