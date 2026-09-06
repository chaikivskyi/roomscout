<?php

namespace App\Tests\Unit\Catalog\Repository;

use App\Catalog\Entity\Product;
use App\Catalog\Repository\ProductCriteriaFilter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ProductCriteriaFilterTest extends KernelTestCase
{
    private function builder(): QueryBuilder
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        return $em->createQueryBuilder()
            ->select('p')
            ->from(Product::class, 'p');
    }

    public function testAddsNoPredicateWhenEveryFilterIsNull(): void
    {
        $qb = $this->builder();

        ProductCriteriaFilter::apply($qb, 'p', null, null, null);

        self::assertNull($qb->getDQLPart('where'));
        self::assertCount(0, $qb->getParameters());
    }

    public function testFiltersOnTheMinimumPrice(): void
    {
        $qb = $this->builder();

        ProductCriteriaFilter::apply($qb, 'p', 100, null, null);

        self::assertStringContainsString('p.price >= :priceMin', $qb->getDQL());
        self::assertSame(100, $qb->getParameter('priceMin')?->getValue());
    }

    public function testFiltersOnTheMaximumPrice(): void
    {
        $qb = $this->builder();

        ProductCriteriaFilter::apply($qb, 'p', null, 500, null);

        self::assertStringContainsString('p.price <= :priceMax', $qb->getDQL());
        self::assertSame(500, $qb->getParameter('priceMax')?->getValue());
    }

    public function testFiltersOnCategoryIds(): void
    {
        $qb = $this->builder();
        $ids = ['0198f7c0-0000-7000-8000-000000000001', '0198f7c0-0000-7000-8000-000000000002'];

        ProductCriteriaFilter::apply($qb, 'p', null, null, $ids);

        self::assertStringContainsString('p.category IN (:categoryIds)', $qb->getDQL());
        self::assertSame($ids, $qb->getParameter('categoryIds')?->getValue());
    }

    public function testCombinesEveryFilter(): void
    {
        $qb = $this->builder();

        ProductCriteriaFilter::apply($qb, 'p', 100, 500, ['0198f7c0-0000-7000-8000-000000000001']);

        $dql = $qb->getDQL();
        self::assertStringContainsString('p.price >= :priceMin', $dql);
        self::assertStringContainsString('p.price <= :priceMax', $dql);
        self::assertStringContainsString('p.category IN (:categoryIds)', $dql);
        self::assertCount(3, $qb->getParameters());
    }

    public function testHonoursTheGivenAlias(): void
    {
        self::bootKernel();

        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');

        $qb = $em->createQueryBuilder()
            ->select('prod')
            ->from(Product::class, 'prod')
            ->join('prod.category', 'c');

        ProductCriteriaFilter::apply($qb, 'prod', 100, null, null);

        self::assertStringContainsString('prod.price >= :priceMin', $qb->getDQL());
    }

    public function testPreservesPredicatesAlreadyOnTheBuilder(): void
    {
        $qb = $this->builder()
            ->where('p.externalId = :externalId')
            ->setParameter('externalId', 'sku-1');

        ProductCriteriaFilter::apply($qb, 'p', 100, null, null);

        $dql = $qb->getDQL();
        self::assertStringContainsString('p.externalId = :externalId', $dql);
        self::assertStringContainsString('p.price >= :priceMin', $dql);
    }
}
