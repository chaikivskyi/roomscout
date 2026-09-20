<?php

namespace App\Tests\Application\Visualization;

use App\Visualization\Command\GenerateVisualizationImage;
use App\Visualization\Enum\VisualizationStatus;
use App\Visualization\EventListener\MarkVisualizationFailedOnGenerationFailure;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductVisualizationFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class MarkVisualizationFailedOnGenerationFailureTest extends ApiTestCase
{
    public function testFinalFailureMarksTheVisualizationFailed(): void
    {
        $visualization = ProductVisualizationFactory::createOne();

        $this->listener()($this->failedEvent($visualization->getId()->toRfc4122()));

        self::assertSame(VisualizationStatus::Failed, $visualization->getStatus());
    }

    public function testFailureThatWillRetryIsIgnored(): void
    {
        $visualization = ProductVisualizationFactory::createOne();

        $event = $this->failedEvent($visualization->getId()->toRfc4122());
        $event->setForRetry();
        $this->listener()($event);

        self::assertSame(VisualizationStatus::Processing, $visualization->getStatus());
    }

    public function testCompletedVisualizationIsNeverDowngraded(): void
    {
        $visualization = ProductVisualizationFactory::new()->completed()->create();

        $this->listener()($this->failedEvent($visualization->getId()->toRfc4122()));

        self::assertSame(VisualizationStatus::Completed, $visualization->getStatus());
    }

    public function testMalformedIdAndUnrelatedMessagesAreIgnored(): void
    {
        $this->listener()($this->failedEvent('not-a-uuid'));

        $event = new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async_visualizations', new \RuntimeException('boom'));
        $this->listener()($event);

        $this->expectNotToPerformAssertions();
    }

    private function failedEvent(string $visualizationId): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(
            new Envelope(new GenerateVisualizationImage($visualizationId)),
            'async_visualizations',
            new \RuntimeException('generation blew up'),
        );
    }

    private function listener(): MarkVisualizationFailedOnGenerationFailure
    {
        return static::getContainer()->get(MarkVisualizationFailedOnGenerationFailure::class);
    }
}
