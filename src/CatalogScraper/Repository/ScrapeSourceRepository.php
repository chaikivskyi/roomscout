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
     * @return list<ScrapeSource>
     */
    public function findActive(): array
    {
        return $this->findBy(['active' => true]);
    }
}
