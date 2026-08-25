<?php

namespace App\Placement\Repository;

use App\Placement\Entity\ProductPlacement;
use App\Placement\Enum\PlacementStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProductPlacement>
 */
class ProductPlacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductPlacement::class);
    }

    public function save(ProductPlacement $placement): void
    {
        $this->getEntityManager()->persist($placement);
        $this->getEntityManager()->flush();
    }

    public function hasActiveForProject(Uuid $projectId): bool
    {
        return (bool) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT EXISTS(SELECT 1 FROM product_placement WHERE project_id = :projectId AND status = :status)',
            [
                'projectId' => $projectId->toRfc4122(),
                'status' => PlacementStatus::Processing->value,
            ],
        );
    }
}
