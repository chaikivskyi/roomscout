<?php

namespace App\Catalog\Repository;

use App\Catalog\Dto\ProductCriteria;
use App\Catalog\Entity\Product;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\Tools\Pagination\Paginator;
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

    /**
     * @return array{items: list<Product>, total: int}
     */
    public function findPage(ProductCriteria $criteria): array
    {
        $qb = $this->createQueryBuilder('p')
            ->addSelect('c')
            ->join('p.category', 'c');

        if (null !== $criteria->priceMin) {
            $qb->andWhere('p.price >= :priceMin')->setParameter('priceMin', $criteria->priceMin);
        }

        if (null !== $criteria->priceMax) {
            $qb->andWhere('p.price <= :priceMax')->setParameter('priceMax', $criteria->priceMax);
        }

        if (null !== $criteria->categoryIds) {
            $qb->andWhere('p.category IN (:categoryIds)')
                ->setParameter('categoryIds', $criteria->categoryIds, ArrayParameterType::STRING);
        }

        $qb->orderBy('p.id', 'DESC')
            ->setFirstResult(($criteria->page - 1) * $criteria->limit)
            ->setMaxResults($criteria->limit);

        /** @var Paginator<Product> $paginator */
        $paginator = new Paginator($qb->getQuery(), fetchJoinCollection: false);

        return [
            'items' => array_values(iterator_to_array($paginator)),
            'total' => \count($paginator),
        ];
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
