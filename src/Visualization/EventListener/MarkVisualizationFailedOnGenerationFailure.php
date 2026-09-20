<?php

namespace App\Visualization\EventListener;

use App\Api\Messenger\FinalFailureMarker;
use App\Visualization\Command\GenerateVisualizationImage;
use App\Visualization\Entity\ProductVisualization;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsEventListener(event: WorkerMessageFailedEvent::class)]
final class MarkVisualizationFailedOnGenerationFailure
{
    public function __construct(
        private readonly FinalFailureMarker $marker,
    ) {
    }

    public function __invoke(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();

        if (!$message instanceof GenerateVisualizationImage) {
            return;
        }

        $this->marker->markFailed(
            $event,
            ProductVisualization::class,
            $message->visualizationId,
            'Visualization generation failed permanently; visualization marked failed.',
            ['visualizationId' => $message->visualizationId],
        );
    }
}
