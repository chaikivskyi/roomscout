<?php

namespace App\Tests\Application;

use App\CatalogScraper\Service\PageFetcher;
use App\CatalogSearch\Service\ImageEmbedderInterface;
use App\Placement\Service\ProductImageComposerInterface;
use App\Tests\Fake\FakeImageEmbedder;
use App\Tests\Fake\FakeProductImageComposer;
use App\Tests\Fake\GuardedHttpClient;

final class NoExternalHttpTest extends ApiTestCase
{
    public function testCohereClientRefusesToLeaveTheTestSuite(): void
    {
        $this->assertGuarded('cohere.client');
    }

    public function testGeminiClientRefusesToLeaveTheTestSuite(): void
    {
        $this->assertGuarded('gemini.client');
    }

    public function testScraperClientRefusesToLeaveTheTestSuite(): void
    {
        $this->assertGuarded('scraper.client');
    }

    public function testThePageFetcherCannotReachTheNetwork(): void
    {
        $fetcher = static::getContainer()->get(PageFetcher::class);
        self::assertInstanceOf(PageFetcher::class, $fetcher);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('scraper.client');
        $fetcher->fetch('http://127.0.0.1:1/products');
    }

    public function testTheEmbedderIsAFakeInTests(): void
    {
        self::assertInstanceOf(
            FakeImageEmbedder::class,
            static::getContainer()->get(ImageEmbedderInterface::class),
        );
    }

    public function testTheImageComposerIsAFakeInTests(): void
    {
        self::assertInstanceOf(
            FakeProductImageComposer::class,
            static::getContainer()->get(ProductImageComposerInterface::class),
        );
    }

    private function assertGuarded(string $clientId): void
    {
        $client = static::getContainer()->get($clientId);
        self::assertInstanceOf(GuardedHttpClient::class, $client, 'Without the guard the next line would hit the network.');

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage($clientId);
        $client->request('GET', '/ping');
    }
}
