<?php

namespace App\CatalogScraper\Service;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PageFetcher
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function fetch(string $url): Crawler
    {
        $response = $this->httpClient->request('GET', $url);

        $html = $response->getContent();

        return new Crawler($html, $url);
    }
}
