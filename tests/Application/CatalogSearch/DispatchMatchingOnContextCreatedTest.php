<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Command\MatchContextProducts;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectContextFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DispatchMatchingOnContextCreatedTest extends ApiTestCase
{
    public function testCreatingContextQueuesMatchingMessage(): void
    {
        $context = ProjectContextFactory::createOne();

        $messages = $this->matchingMessages();
        self::assertCount(1, $messages);
        self::assertSame($context->getId()->toRfc4122(), $messages[0]->contextId);
    }

    public function testUpdatingContextQueuesNothing(): void
    {
        $context = ProjectContextFactory::createOne();
        $sentAfterCreate = \count($this->matchingMessages());

        $context->markCompleted();
        $this->entityManager()->flush();

        self::assertCount($sentAfterCreate, $this->matchingMessages());
    }

    public function testMatchingDoesNotShareTheEmbeddingQueue(): void
    {
        ProjectContextFactory::createOne();

        $embeddingTransport = static::getContainer()->get('messenger.transport.async_embeddings');
        \assert($embeddingTransport instanceof InMemoryTransport);

        foreach ($embeddingTransport->getSent() as $envelope) {
            self::assertNotInstanceOf(MatchContextProducts::class, $envelope->getMessage());
        }

        self::assertCount(1, $this->matchingMessages());
    }

    /**
     * @return list<MatchContextProducts>
     */
    private function matchingMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_matching');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof MatchContextProducts) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
