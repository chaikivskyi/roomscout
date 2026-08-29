<?php

namespace App\Placement\EventListener;

use App\Api\Messenger\FinalFailureMarker;
use App\Placement\Command\GeneratePlacementImage;
use App\Placement\Entity\ProductPlacement;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkPlacementFailedOnGenerationFailure
{
    public function __construct(
        private readonly FinalFailureMarker $marker,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof GeneratePlacementImage) {
            return;
        }

        $this->marker->markFailed(
            $event,
            ProductPlacement::class,
            $message->placementId,
            'Placement generation failed permanently; placement marked failed.',
            ['placementId' => $message->placementId],
        );
    }
}
