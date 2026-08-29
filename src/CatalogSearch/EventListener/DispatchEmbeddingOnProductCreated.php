<?php

namespace App\CatalogSearch\EventListener;

use App\Api\Bus\CommandBusInterface;
use App\Catalog\Entity\Product;
use App\CatalogSearch\Command\EmbedProductThumbnail;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

#[AsEntityListener(event: Events::postPersist, method: 'postPersist', entity: Product::class)]
final class DispatchEmbeddingOnProductCreated
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
    ) {
    }

    public function postPersist(Product $product): void
    {
        $this->commandBus->dispatch(new EmbedProductThumbnail((string) $product->getId()));
    }
}
