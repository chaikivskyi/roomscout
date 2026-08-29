<?php

namespace App\CatalogSearch\EventListener;

use App\Api\Messenger\FinalFailureMarker;
use App\CatalogSearch\Command\MatchContextProducts;
use App\Project\Entity\ProjectContext;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkContextFailedOnMatchingFailure
{
    public function __construct(
        private readonly FinalFailureMarker $marker,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof MatchContextProducts) {
            return;
        }

        $this->marker->markFailed(
            $event,
            ProjectContext::class,
            $message->contextId,
            'Matching failed permanently; context marked failed.',
            ['contextId' => $message->contextId],
        );
    }
}
