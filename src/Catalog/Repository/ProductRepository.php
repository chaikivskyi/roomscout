<?php

namespace App\Catalog\Repository;

use App\Catalog\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function findOneByExternalId(string $externalId): ?Product
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function save(Product $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }

    public function discardChanges(Product $product): void
    {
        $em = $this->getEntityManager();

        if (!$em->contains($product)) {
            return;
        }

        try {
            $em->refresh($product);
        } catch (\Throwable) {
            $em->detach($product);
        }
    }
}
