<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Message\MatchProjectProductsMessage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProjectFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DispatchMatchingOnProjectCreatedTest extends ApiTestCase
{
    public function testCreatingProjectQueuesMatchingMessage(): void
    {
        $project = ProjectFactory::createOne();

        $messages = $this->matchingMessages();
        self::assertCount(1, $messages);
        self::assertSame($project->getId()->toRfc4122(), $messages[0]->projectId);
    }

    public function testUpdatingProjectQueuesNothing(): void
    {
        $project = ProjectFactory::createOne();
        $sentAfterCreate = \count($this->matchingMessages());

        $project->setPrompt('changed prompt');
        $this->entityManager()->flush();

        self::assertCount($sentAfterCreate, $this->matchingMessages());
    }

    /**
     * @return list<MatchProjectProductsMessage>
     */
    private function matchingMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_embeddings');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof MatchProjectProductsMessage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
