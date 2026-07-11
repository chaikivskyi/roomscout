<?php

namespace App\CatalogScraper\MessageHandler;

use App\CatalogScraper\Message\ScrapeSourceMessage;
use App\CatalogScraper\Repository\ScrapeSourceRepository;
use App\CatalogScraper\Service\ScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class ScrapeSourceHandler
{
    public function __construct(
        private readonly ScrapeSourceRepository $scrapeSourceRepository,
        private readonly ScraperService $scraperService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScrapeSourceMessage $message): void
    {
        $source = $this->scrapeSourceRepository->find($message->sourceId);

        if (null === $source) {
            $this->logger->warning('Scrape source #{id} no longer exists, skipping.', [
                'id' => $message->sourceId,
            ]);

            return;
        }

        if (!$source->isActive()) {
            $this->logger->info('Scrape source #{id} is inactive, skipping.', [
                'id' => $message->sourceId,
            ]);

            return;
        }

        $this->scraperService->scrape($source);
    }
}
