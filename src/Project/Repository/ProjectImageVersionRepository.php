<?php

namespace App\Project\Repository;

use App\Project\Entity\ProjectImageVersion;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProjectImageVersion>
 */
class ProjectImageVersionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProjectImageVersion::class);
    }

    public function findLatestForProject(Uuid $projectId): ?ProjectImageVersion
    {
        return $this->findOneBy(['project' => $projectId], ['id' => 'DESC']);
    }
}
