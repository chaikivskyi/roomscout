<?php

namespace App\CatalogScraper\Repository;

use App\CatalogScraper\Entity\ScrapeSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ScrapeSource>
 */
class ScrapeSourceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ScrapeSource::class);
    }

    /**
     * @return ScrapeSource[]
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('ss')
            ->andWhere('ss.active = true')
            ->orderBy('ss.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
