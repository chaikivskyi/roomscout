<?php

namespace App\Project\Repository;

use App\Project\Entity\ProjectContext;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
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

    /**
     * @param list<Uuid> $projectIds
     *
     * @return array<string, string>
     */
    public function findInitialPromptsForProjects(array $projectIds): array
    {
        if ([] === $projectIds) {
            return [];
        }

        /** @var list<array{project_id: string, prompt: string}> $rows */
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT DISTINCT ON (project_id) project_id, prompt
                FROM project_context
                WHERE project_id IN (:ids)
                ORDER BY project_id, id ASC
                SQL,
            ['ids' => array_map(static fn (Uuid $id): string => (string) $id, $projectIds)],
            ['ids' => ArrayParameterType::STRING],
        );

        return array_column($rows, 'prompt', 'project_id');
    }
}
