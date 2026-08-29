<?php

namespace App\Project\Repository;

use App\Project\Entity\ProjectContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectContext>
 */
class ProjectContextRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectContext::class);
    }

    public function save(ProjectContext $context): void
    {
        $this->getEntityManager()->persist($context);
        $this->getEntityManager()->flush();
    }

    public function findOneForProject(Uuid $projectId, Uuid $contextId): ?ProjectContext
    {
        return $this->findOneBy(['id' => $contextId, 'project' => $projectId]);
    }

    /**
     * @return list<ProjectContext>
     */
    public function findAllForProject(Uuid $projectId): array
    {
        return $this->findBy(['project' => $projectId], ['id' => 'ASC']);
    }
}
