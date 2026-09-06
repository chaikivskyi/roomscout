<?php

namespace App\Tests\Unit\Catalog\Dto;

use App\Catalog\Dto\ProductCriteriaRules;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProductCriteriaRulesTest extends TestCase
{
    public function testAcceptsAValidCombination(): void
    {
        ProductCriteriaRules::assert(1, 10, 100, 500);

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsNullPriceBounds(): void
    {
        ProductCriteriaRules::assert(3, 25, null, null);

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsAMinimumPriceEqualToTheMaximum(): void
    {
        ProductCriteriaRules::assert(1, 10, 250, 250);

        $this->expectNotToPerformAssertions();
    }

    public function testAcceptsAZeroPriceBound(): void
    {
        ProductCriteriaRules::assert(1, 10, 0, 0);

        $this->expectNotToPerformAssertions();
    }

    #[DataProvider('invalidPages')]
    public function testRejectsAPageBelowOne(int $page): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf('Page must be at least 1, got %d.', $page));

        ProductCriteriaRules::assert($page, 10, null, null);
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

        ProductCriteriaRules::assert(1, $limit, null, null);
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

        ProductCriteriaRules::assert(1, 10, -1, null);
    }

    public function testRejectsANegativeMaximumPrice(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Price bounds must not be negative.');

        ProductCriteriaRules::assert(1, 10, null, -1);
    }

    public function testRejectsAMinimumPriceAboveTheMaximum(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Minimum price must not exceed maximum price.');

        ProductCriteriaRules::assert(1, 10, 500, 100);
    }

    public function testChecksThePageBeforeTheLimit(): void
    {
        $this->expectExceptionMessage('Page must be at least 1, got 0.');

        ProductCriteriaRules::assert(0, 0, null, null);
    }
}
