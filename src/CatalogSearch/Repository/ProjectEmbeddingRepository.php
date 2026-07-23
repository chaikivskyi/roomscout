<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Entity\ProjectEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectEmbedding>
 */
class ProjectEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectEmbedding::class);
    }

    public function findForProject(Uuid $projectId): ?ProjectEmbedding
    {
        return $this->findOneBy(['project' => $projectId]);
    }

    public function save(ProjectEmbedding $embedding): void
    {
        $this->getEntityManager()->persist($embedding);
        $this->getEntityManager()->flush();
    }
}
