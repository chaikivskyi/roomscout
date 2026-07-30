<?php

namespace App\CatalogSearch\Repository;

use App\CatalogSearch\Entity\ProductEmbedding;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Uid\Uuid;

/**
 * @extends ServiceEntityRepository<ProductEmbedding>
 */
class ProductEmbeddingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProductEmbedding::class);
    }

    public function existsForProduct(Uuid $productId): bool
    {
        return 0 < $this->count(['product' => $productId]);
    }

    public function save(ProductEmbedding $embedding): void
    {
        $this->getEntityManager()->persist($embedding);
        $this->getEntityManager()->flush();
    }
}
