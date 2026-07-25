<?php

namespace App\CatalogScraper\Service;

use App\CatalogScraper\Entity\ScrapeSource;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\UriResolver;

class ProductUrlCollector
{
    public function __construct(
        private PageFetcher $pageFetcher,
    ) {
    }

    /**
     * @return iterable<string>
     */
    public function collect(ScrapeSource $source): iterable
    {
        $productSelector = (string) $source->getProductUrlSelector();
        $nextSelector = $source->getNextPageSelector();

        $pageUrl = $source->getSourceUrl();
        $visited = [];

        while (null !== $pageUrl && !isset($visited[$pageUrl])) {
            $visited[$pageUrl] = true;
            $crawler = $this->pageFetcher->fetch($pageUrl);

            foreach ($crawler->filter($productSelector) as $node) {
                if (!$node instanceof \DOMElement) {
                    continue;
                }

                $href = $node->getAttribute('href');

                if ($href) {
                    yield UriResolver::resolve($href, $pageUrl);
                }
            }

            $pageUrl = $nextSelector ? $this->findNextPageUrl($crawler, $nextSelector, $pageUrl) : null;
        }
    }

    private function findNextPageUrl(Crawler $crawler, string $nextSelector, string $baseUrl): ?string
    {
        $next = $crawler->filter($nextSelector);

        if (0 === $next->count()) {
            return null;
        }

        $href = $next->first()->attr('href');

        if (empty($href)) {
            return null;
        }

        return UriResolver::resolve($href, $baseUrl);
    }
}
