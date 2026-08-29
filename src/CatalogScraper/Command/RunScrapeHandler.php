<?php

namespace App\CatalogScraper\Command;

use App\Api\Bus\CommandBusInterface;
use App\CatalogScraper\Repository\ScrapeSourceRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final class RunScrapeHandler
{
    public function __construct(
        private readonly ScrapeSourceRepository $scrapeSourceRepository,
        private readonly CommandBusInterface $commandBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(RunScrape $command): void
    {
        $sources = $this->scrapeSourceRepository->findActive();

        foreach ($sources as $source) {
            $this->commandBus->dispatch(new ScrapeSource((string) $source->getId()));
        }

        $this->logger->info('Dispatched {count} scrape source job(s).', [
            'count' => count($sources),
        ]);
    }
}
