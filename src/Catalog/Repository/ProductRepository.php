<?php

namespace App\Catalog\Repository;

use App\Catalog\Api\ProductInterface;
use App\Catalog\Api\ProductRepositoryInterface;
use App\Catalog\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Product>
 */
class ProductRepository extends ServiceEntityRepository implements ProductRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Product::class);
    }

    public function create(): ProductInterface
    {
        return new Product();
    }

    public function findOneByExternalId(string $externalId): ?ProductInterface
    {
        return $this->findOneBy(['externalId' => $externalId]);
    }

    public function save(ProductInterface $product): void
    {
        $this->getEntityManager()->persist($product);
        $this->getEntityManager()->flush();
    }
}
