<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Entity\ProjectContextEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectContextEmbedding>
 */
class ProjectContextEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectContextEmbedding::class);
    }

    public function findForContext(Uuid $contextId): ?ProjectContextEmbedding
    {
        return $this->findOneBy(['context' => $contextId]);
    }

    public function save(ProjectContextEmbedding $embedding): void
    {
        $this->getEntityManager()->persist($embedding);
        $this->getEntityManager()->flush();
    }
}
