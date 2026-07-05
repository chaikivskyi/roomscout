<?php

namespace App\CatalogScraper\MessageHandler;

use App\CatalogScraper\Message\RunScrapeMessage;
use App\CatalogScraper\Service\ScraperService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class RunScrapeHandler
{
    public function __construct(
        private readonly ScraperService $scraperService,
    ) {
    }

    public function __invoke(RunScrapeMessage $message): void
    {
        $this->scraperService->run();
    }
}
