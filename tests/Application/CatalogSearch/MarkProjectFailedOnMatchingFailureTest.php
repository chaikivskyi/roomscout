<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\EventListener\MarkProjectFailedOnMatchingFailure;
use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\Project\Enum\ProjectStatus;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Uid\Uuid;

final class MarkProjectFailedOnMatchingFailureTest extends ApiTestCase
{
    public function testFinalFailureMarksProjectFailed(): void
    {
        $project = ProjectFactory::createOne();

        $this->listener()($this->failedEvent($project->getId()->toRfc4122()));

        self::assertSame(ProjectStatus::Failed, $project->getStatus());
    }

    public function testIntermediateFailureKeepsProcessing(): void
    {
        $project = ProjectFactory::createOne();

        $event = $this->failedEvent($project->getId()->toRfc4122());
        $event->setForRetry();
        $this->listener()($event);

        self::assertSame(ProjectStatus::Processing, $project->getStatus());
    }

    public function testCompletedProjectIsNotOverwritten(): void
    {
        $project = ProjectFactory::createOne();
        $project->markCompleted();
        $this->entityManager()->flush();

        $this->listener()($this->failedEvent($project->getId()->toRfc4122()));

        self::assertSame(ProjectStatus::Completed, $project->getStatus());
    }

    public function testOtherMessagesAreIgnored(): void
    {
        $project = ProjectFactory::createOne();

        $this->listener()(new WorkerMessageFailedEvent(
            new Envelope(new \stdClass()),
            'async_embeddings',
            new \RuntimeException('boom'),
        ));

        self::assertSame(ProjectStatus::Processing, $project->getStatus());
    }

    public function testUnknownProjectIsIgnored(): void
    {
        $this->listener()($this->failedEvent(Uuid::v7()->toRfc4122()));

        $this->expectNotToPerformAssertions();
    }

    private function failedEvent(string $projectId): WorkerMessageFailedEvent
    {
        return new WorkerMessageFailedEvent(
            new Envelope(new MatchProjectProductsMessage($projectId)),
            'async_embeddings',
            new \RuntimeException('boom'),
        );
    }

    private function listener(): MarkProjectFailedOnMatchingFailure
    {
        return static::getContainer()->get(MarkProjectFailedOnMatchingFailure::class);
    }
}
