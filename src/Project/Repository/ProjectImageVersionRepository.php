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

    /**
     * @param list<Uuid> $projectIds
     *
     * @return array<string, string>
     */
    public function findLatestPathsForProjects(array $projectIds): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{project_id: string, image_path: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT i.project_id, v.image_path
                FROM unnest(CAST(:ids AS uuid[])) AS i(project_id)
                CROSS JOIN LATERAL (
                    SELECT image_path
                    FROM project_image_version
                    WHERE project_id = i.project_id
                    ORDER BY id DESC
                    LIMIT 1
                ) v
                SQL,
            ['ids' => sprintf('{%s}', implode(',', array_map(static fn (Uuid $id): string => (string) $id, $projectIds)))],
        );

        return array_column($rows, 'image_path', 'project_id');
    }
}
