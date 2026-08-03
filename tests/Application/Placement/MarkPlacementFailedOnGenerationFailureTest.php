<?php

namespace App\Tests\Application\Placement;

use App\Placement\Enum\PlacementStatus;
use App\Placement\EventListener\MarkPlacementFailedOnGenerationFailure;
use App\Placement\Message\GeneratePlacementImageMessage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductPlacementFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class MarkPlacementFailedOnGenerationFailureTest extends ApiTestCase
{
    public function testFinalFailureMarksThePlacementFailed(): void
    {
        $placement = ProductPlacementFactory::createOne();

        $this->listener()($this->failedEvent($placement->getId()->toRfc4122()));

        // Failing the placement is what releases the project's 409 lock.
        self::assertSame(PlacementStatus::Failed, $placement->getStatus());
    }

    public function testFailureThatWillRetryIsIgnored(): void
    {
        $placement = ProductPlacementFactory::createOne();

        $event = $this->failedEvent($placement->getId()->toRfc4122());
        $event->setForRetry();
        $this->listener()($event);

        self::assertSame(PlacementStatus::Processing, $placement->getStatus());
    }

    public function testCompletedPlacementIsNeverDowngraded(): void
    {
        $placement = ProductPlacementFactory::new()->completed()->create();

        $this->listener()($this->failedEvent($placement->getId()->toRfc4122()));

        self::assertSame(PlacementStatus::Completed, $placement->getStatus());
    }

    public function testMalformedIdAndUnrelatedMessagesAreIgnored(): void
    {
        $this->listener()($this->failedEvent('not-a-uuid'));

        $event = new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async_placements', new \RuntimeException('boom'));
        $this->listener()($event);

        $this->expectNotToPerformAssertions();
    }

    private function failedEvent(string $placementId): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(
            new Envelope(new GeneratePlacementImageMessage($placementId)),
            'async_placements',
            new \RuntimeException('generation blew up'),
        );
    }

    private function listener(): MarkPlacementFailedOnGenerationFailure
    {
        return static::getContainer()->get(MarkPlacementFailedOnGenerationFailure::class);
    }
}
