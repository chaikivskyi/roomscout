<?php

namespace App\Tests\Application\CatalogSearch;

use App\CatalogSearch\Message\EmbedProductThumbnailMessage;
use App\Tests\Application\ApiTestCase;
use App\Tests\Factory\ProductFactory;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

final class DispatchEmbeddingOnProductCreatedTest extends ApiTestCase
{
    public function testCreatingProductQueuesEmbeddingMessage(): void
    {
        $product = ProductFactory::createOne();

        $messages = $this->embeddingMessages();
        self::assertCount(1, $messages);
        self::assertSame($product->getId(), $messages[0]->productId);
    }

    public function testUpdatingProductQueuesNothing(): void
    {
        $product = ProductFactory::createOne();
        $sentAfterCreate = \count($this->embeddingMessages());

        $product->setTitle('renamed');
        $this->entityManager()->flush();

        self::assertCount($sentAfterCreate, $this->embeddingMessages());
    }

    /**
     * @return list<EmbedProductThumbnailMessage>
     */
    private function embeddingMessages(): array
    {
        $transport = static::getContainer()->get('messenger.transport.async_embeddings');
        \assert($transport instanceof InMemoryTransport);

        $messages = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof EmbedProductThumbnailMessage) {
                $messages[] = $message;
            }
        }

        return $messages;
    }
}
