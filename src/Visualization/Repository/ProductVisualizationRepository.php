<?php

namespace App\Visualization\Repository;

use App\Visualization\Entity\ProductVisualization;
use App\Visualization\Enum\VisualizationStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProductVisualization>
 */
class ProductVisualizationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductVisualization::class);
    }

    public function save(ProductVisualization $visualization): void
    {
        $this->getEntityManager()->persist($visualization);
        $this->getEntityManager()->flush();
    }

    public function hasActiveForProject(Uuid $projectId): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM product_visualization WHERE project_id = :projectId AND status = :status)',
            [
                'projectId' => $projectId->toRfc4122(),
                'status' => VisualizationStatus::Processing->value,
            ],
        );
    }
}
