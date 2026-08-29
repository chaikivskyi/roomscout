<?php

namespace App\CatalogScraper\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_scraping')]
final class ScrapeSource implements CommandInterface
{
    public function __construct(
        public readonly string $sourceId,
    ) {
    }
}
