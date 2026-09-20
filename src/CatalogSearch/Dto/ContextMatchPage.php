<?php

namespace App\CatalogSearch\Dto;

use App\CatalogSearch\ApiResource\ContextMatch;

final class ContextMatchPage
{
    /**
     * @param list<ContextMatch> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
