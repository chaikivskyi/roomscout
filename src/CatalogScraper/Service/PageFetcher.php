<?php

namespace App\CatalogScraper\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PageFetcher
{
    private const MAX_BYTES = 5 * 1024 * 1024;

    public function __construct(
        private readonly HttpClientInterface $scraperClient,
        private readonly CappedResponseReader $responseReader,
    ) {
    }

    public function fetch(string $url): Crawler
    {
        $response = $this->scraperClient->request('GET', $url, ['buffer' => false]);

        $response->getHeaders();

        return new Crawler($this->responseReader->read($response, self::MAX_BYTES), $url);
    }
}
