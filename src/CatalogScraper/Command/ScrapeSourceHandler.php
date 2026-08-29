<?php

namespace App\CatalogScraper\Command;

use App\CatalogScraper\Repository\ScrapeSourceRepository;
use App\CatalogScraper\Service\ScraperService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\UnrecoverableMessageHandlingException;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler(bus: 'command.bus')]
final class ScrapeSourceHandler
{
    public function __construct(
        private readonly ScrapeSourceRepository $scrapeSourceRepository,
        private readonly ScraperService $scraperService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(ScrapeSource $command): void
    {
        $source = $this->scrapeSourceRepository->find($this->parseSourceId($command->sourceId));

        if (null === $source) {
            $this->logger->warning('Scrape source #{id} no longer exists, skipping.', [
                'id' => $command->sourceId,
            ]);

            return;
        }

        if (!$source->isActive()) {
            $this->logger->info('Scrape source #{id} is inactive, skipping.', [
                'id' => $command->sourceId,
            ]);

            return;
        }

        $this->scraperService->scrape($source);
    }

    private function parseSourceId(string $sourceId): Uuid
    {
        try {
            return Uuid::fromString($sourceId);
        } catch (\InvalidArgumentException $e) {
            throw new UnrecoverableMessageHandlingException(sprintf('Malformed scrape source id "%s".', $sourceId), previous: $e);
        }
    }
}
