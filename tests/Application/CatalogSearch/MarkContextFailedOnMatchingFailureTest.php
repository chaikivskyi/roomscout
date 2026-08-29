<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Command\MatchContextProducts;
use App\CatalogSearch\EventListener\MarkContextFailedOnMatchingFailure;
use App\Project\Enum\ProjectContextStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

final class MarkContextFailedOnMatchingFailureTest extends ApiTestCase
{
    public function testFinalFailureMarksTheContextFailed(): void
    {
        $context = ProjectContextFactory::createOne();

        $this->listener()($this->failedEvent($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Failed, $context->getStatus());
    }

    public function testFailureThatWillRetryIsIgnored(): void
    {
        $context = ProjectContextFactory::createOne();

        $event = $this->failedEvent($context->getId()->toRfc4122());
        $event->setForRetry();
        $this->listener()($event);

        self::assertSame(ProjectContextStatus::Processing, $context->getStatus());
    }

    public function testCompletedContextIsNeverDowngraded(): void
    {
        $context = ProjectContextFactory::new()->completed()->create();

        $this->listener()($this->failedEvent($context->getId()->toRfc4122()));

        self::assertSame(ProjectContextStatus::Completed, $context->getStatus());
    }

    public function testMalformedIdAndUnrelatedMessagesAreIgnored(): void
    {
        $this->listener()($this->failedEvent('not-a-uuid'));

        $event = new WorkerMessageFailedEvent(new Envelope(new \stdClass()), 'async_matching', new \RuntimeException('boom'));
        $this->listener()($event);

        $this->expectNotToPerformAssertions();
    }

    private function failedEvent(string $contextId): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(
            new Envelope(new MatchContextProducts($contextId)),
            'async_matching',
            new \RuntimeException('matching blew up'),
        );
    }

    private function listener(): MarkContextFailedOnMatchingFailure
    {
        return static::getContainer()->get(MarkContextFailedOnMatchingFailure::class);
    }
}
