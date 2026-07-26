<?php

namespace App\Catalog\EventListener;

use App\Catalog\Entity\Product;
use App\Catalog\Service\ProductThumbnailHasher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
final class ComputeProductThumbnailHashListener
{
    public function __construct(
        private readonly ProductThumbnailHasher $hasher,
    ) {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ([...$uow->getScheduledEntityInsertions(), ...$uow->getScheduledEntityUpdates()] as $entity) {
            if (!$entity instanceof Product) {
                continue;
            }

            $hash = $this->hasher->hashFor($entity->getThumbnailUrl());

            if ($hash === $entity->getThumbnailHash()) {
                continue;
            }

            $entity->setThumbnailHash($hash);
            $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Product::class), $entity);
        }
    }
}
