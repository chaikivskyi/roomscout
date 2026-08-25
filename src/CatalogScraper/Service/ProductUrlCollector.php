<?php

namespace App\CatalogScraper\Service;

use App\CatalogScraper\Entity\ScrapeSource;
use Psr\Log\LoggerInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class ProductUrlCollector
{
    private const MAX_PAGES = 100;

    private const MAX_PRODUCT_URLS = 5000;

    public function __construct(
        private PageFetcher $pageFetcher,
        private LoggerInterface $logger,
    ) {
    }

    /**
     * @return iterable<string>
     */
    public function collect(ScrapeSource $source): iterable
    {
        $productSelector = (string) $source->getProductUrlSelector();
        $seenProductUrls = [];
        $isFirstPage = true;

        foreach ($this->crawlListingPages($source) as $listingPageUrl => $crawler) {
            try {
                $productUrls = $this->extractProductUrls($crawler, $productSelector, $listingPageUrl);
            } catch (\Throwable $e) {
                if ($isFirstPage) {
                    throw $e;
                }

                $this->logger->error('Failed to parse listing page {url}: {message}', [
                    'url' => $listingPageUrl,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            foreach ($productUrls as $productUrl) {
                if (isset($seenProductUrls[$productUrl])) {
                    continue;
                }

                if (count($seenProductUrls) >= self::MAX_PRODUCT_URLS) {
                    $this->logger->warning('Reached the {max}-product limit for "{title}", stopping at {url}.', [
                        'max' => self::MAX_PRODUCT_URLS,
                        'title' => $source->getTitle(),
                        'url' => $listingPageUrl,
                    ]);

                    return;
                }

                $seenProductUrls[$productUrl] = true;

                yield $productUrl;
            }

            $isFirstPage = false;
        }
    }

    /**
     * @return iterable<string, Crawler>
     */
    private function crawlListingPages(ScrapeSource $source): iterable
    {
        $nextSelector = $source->getNextPageSelector();
        $nextPageUrl = $source->getSourceUrl();
        $visitedPages = [];

        while (null !== $nextPageUrl) {
            $listingPageUrl = $nextPageUrl;

            if (isset($visitedPages[$listingPageUrl])) {
                $this->logger->warning('Pagination for "{title}" loops back to {url}, stopping.', [
                    'title' => $source->getTitle(),
                    'url' => $listingPageUrl,
                ]);

                return;
            }

            if (count($visitedPages) >= self::MAX_PAGES) {
                $this->logger->warning('Reached the {max}-page limit for "{title}", stopping at {url}.', [
                    'max' => self::MAX_PAGES,
                    'title' => $source->getTitle(),
                    'url' => $listingPageUrl,
                ]);

                return;
            }

            $isFirstPage = [] === $visitedPages;
            $visitedPages[$listingPageUrl] = true;

            try {
                $crawler = $this->pageFetcher->fetch($listingPageUrl);
            } catch (\Throwable $e) {
                if ($isFirstPage) {
                    throw $e;
                }

                $this->logger->error('Failed to fetch listing page {url}: {message}', [
                    'url' => $listingPageUrl,
                    'message' => $e->getMessage(),
                ]);

                return;
            }

            yield $listingPageUrl => $crawler;

            try {
                $nextPageUrl = empty($nextSelector)
                    ? null
                    : $this->findNextPageUrl($crawler, $nextSelector, $listingPageUrl);
            } catch (\Throwable $e) {
                $this->logger->error('Failed to resolve the next-page link on {url}: {message}', [
                    'url' => $listingPageUrl,
                    'message' => $e->getMessage(),
                ]);

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function extractProductUrls(Crawler $crawler, string $productSelector, string $listingPageUrl): array
    {
        $productUrls = [];

        foreach ($crawler->filter($productSelector) as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $href = $node->getAttribute('href');

            if ($href) {
                $productUrls[] = UriResolver::resolve($href, $listingPageUrl);
            }
        }

        return $productUrls;
    }

    private function findNextPageUrl(Crawler $crawler, string $nextSelector, string $listingPageUrl): ?string
    {
        $next = $crawler->filter($nextSelector);

        if (0 === $next->count()) {
            return null;
        }

        $href = $next->first()->attr('href');

        if (empty($href)) {
            return null;
        }

        return UriResolver::resolve($href, $listingPageUrl);
    }
}
