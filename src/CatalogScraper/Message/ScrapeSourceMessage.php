<?php

namespace App\CatalogScraper\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class ScrapeSourceMessage
{
    public function __construct(
        public readonly int $sourceId,
    ) {
    }
}
