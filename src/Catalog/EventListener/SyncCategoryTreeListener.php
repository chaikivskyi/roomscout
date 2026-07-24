<?php

namespace App\Catalog\EventListener;

use App\Catalog\Entity\Category;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class SyncCategoryTreeListener
{
    /**
     * @var list<int>
     */
    private array $changedSubtreeRootIds = [];

    public function onFlush(OnFlushEventArgs $args): void
    {
        $this->changedSubtreeRootIds = [];

        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            if ($entity instanceof Category && $entity->refreshTreeFields()) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Category::class), $entity);
            }
        }

        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if (!$entity instanceof Category) {
                continue;
            }

            $changeSet = $uow->getEntityChangeSet($entity);
            if (!isset($changeSet['title']) && !isset($changeSet['parent'])) {
                continue;
            }

            if ($entity->refreshTreeFields()) {
                $uow->recomputeSingleEntityChangeSet($em->getClassMetadata(Category::class), $entity);
                $this->changedSubtreeRootIds[] = $entity->getId();
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if ([] === $this->changedSubtreeRootIds) {
            return;
        }

        $ids = $this->changedSubtreeRootIds;
        $this->changedSubtreeRootIds = [];

        $args->getObjectManager()->getConnection()->executeStatement(<<<'SQL'
            WITH RECURSIVE tree AS (
                SELECT id, level, path_title FROM category WHERE id IN (:ids)
                UNION ALL
                SELECT c.id, t.level + 1, t.path_title || ' › ' || c.title
                FROM category c JOIN tree t ON c.parent_category_id = t.id
            )
            UPDATE category SET level = tree.level, path_title = tree.path_title
            FROM tree
            WHERE category.id = tree.id
              AND (category.level <> tree.level OR category.path_title <> tree.path_title)
            SQL, ['ids' => $ids], ['ids' => ArrayParameterType::INTEGER]);
    }
}
