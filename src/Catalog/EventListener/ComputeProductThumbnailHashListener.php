<?php

namespace App\Catalog\EventListener;

use App\Catalog\Entity\Product;
use App\Catalog\Service\ProductThumbnailHasher;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\UnitOfWork;

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

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if (!$entity instanceof Product || null !== $entity->getThumbnailHash()) {
                continue;
            }

            $this->refreshHash($em, $uow, $entity);
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Product) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);

            if (isset($changeSet['thumbnailHash'])) {
                continue;
            }

            if (!isset($changeSet['thumbnailUrl']) && null !== $entity->getThumbnailHash()) {
                continue;
            }

            $this->refreshHash($em, $uow, $entity);
        }
    }

    private function refreshHash(EntityManagerInterface $em, UnitOfWork $uow, Product $entity): void
    {
        $hash = $this->hasher->hashFor($entity->getThumbnailUrl());

        if ($hash === $entity->getThumbnailHash()) {
            return;
        }

        $entity->setThumbnailHash($hash);
        $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Product::class), $entity);
    }
}
