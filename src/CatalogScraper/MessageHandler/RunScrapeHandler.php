<?php

namespace App\CatalogScraper\MessageHandler;

use App\CatalogScraper\Message\RunScrapeMessage;
use App\CatalogScraper\Message\ScrapeSourceMessage;
use App\CatalogScraper\Repository\ScrapeSourceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final class RunScrapeHandler
{
    public function __construct(
        private readonly ScrapeSourceRepository $scrapeSourceRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunScrapeMessage $message): void
    {
        $sources = $this->scrapeSourceRepository->findActive();

        foreach ($sources as $source) {
            $sourceId = $source->getId();
            $this->messageBus->dispatch(new ScrapeSourceMessage($sourceId));
        }

        $this->logger->info('Dispatched {count} scrape source job(s).', [
            'count' => count($sources),
        ]);
    }
}
