<?php

namespace App\CatalogScraper\Dto;

final readonly class StoredThumbnail
{
    public function __construct(
        public string $path,
        public string $hash,
    ) {
    }
}
