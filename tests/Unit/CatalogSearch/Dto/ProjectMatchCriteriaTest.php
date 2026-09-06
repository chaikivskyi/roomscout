<?php

namespace App\Tests\Unit\CatalogSearch\Dto;

use App\CatalogSearch\Dto\ProjectMatchCriteria;
use App\CatalogSearch\Enum\MatchSort;
use App\CatalogSearch\Enum\SortDirection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProjectMatchCriteriaTest extends TestCase
{
    public function testAcceptsAValidPage(): void
    {
        $criteria = new ProjectMatchCriteria(
            page: 2,
            limit: 20,
            priceMin: 100,
            priceMax: 500,
            categoryIds: ['0198f7c0-0000-7000-8000-000000000001'],
            sort: MatchSort::Price,
            direction: SortDirection::Asc,
        );

        self::assertSame(2, $criteria->page);
        self::assertSame(20, $criteria->limit);
        self::assertSame(100, $criteria->priceMin);
        self::assertSame(500, $criteria->priceMax);
        self::assertSame(['0198f7c0-0000-7000-8000-000000000001'], $criteria->categoryIds);
        self::assertSame(MatchSort::Price, $criteria->sort);
        self::assertSame(SortDirection::Asc, $criteria->direction);
    }

    public function testDefaultsToScoreDescending(): void
    {
        $criteria = new ProjectMatchCriteria(page: 1, limit: 10);

        self::assertSame(MatchSort::Score, $criteria->sort);
        self::assertSame(SortDirection::Desc, $criteria->direction);
        self::assertNull($criteria->priceMin);
        self::assertNull($criteria->priceMax);
        self::assertNull($criteria->categoryIds);
    }

    #[DataProvider('invalidPages')]
    public function testRejectsAPageBelowOne(int $page): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Page must be at least 1, got %d.', $page));

        new ProjectMatchCriteria(page: $page, limit: 10);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidPages(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-3];
    }

    #[DataProvider('invalidLimits')]
    public function testRejectsALimitBelowOne(int $limit): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Limit must be at least 1, got %d.', $limit));

        new ProjectMatchCriteria(page: 1, limit: $limit);
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
    }

    public function testRejectsANegativeMinimumPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price bounds must not be negative.');

        new ProjectMatchCriteria(page: 1, limit: 10, priceMin: -1);
    }

    public function testRejectsANegativeMaximumPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price bounds must not be negative.');

        new ProjectMatchCriteria(page: 1, limit: 10, priceMax: -1);
    }

    public function testRejectsAMinimumPriceAboveTheMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum price must not exceed maximum price.');

        new ProjectMatchCriteria(page: 1, limit: 10, priceMin: 500, priceMax: 100);
    }

    public function testAcceptsAMinimumPriceEqualToTheMaximum(): void
    {
        $criteria = new ProjectMatchCriteria(page: 1, limit: 10, priceMin: 250, priceMax: 250);

        self::assertSame(250, $criteria->priceMin);
        self::assertSame(250, $criteria->priceMax);
    }
}
