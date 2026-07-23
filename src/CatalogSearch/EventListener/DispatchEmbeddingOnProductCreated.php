<?php

namespace App\CatalogSearch\EventListener;

use App\Catalog\Entity\Product;
use App\CatalogSearch\Message\EmbedProductThumbnailMessage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Product::class)]
final class DispatchEmbeddingOnProductCreated
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function postPersist(Product $product): void
    {
        $this->messageBus->dispatch(new EmbedProductThumbnailMessage($product->getId()));
    }
}
