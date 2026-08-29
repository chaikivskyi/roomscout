<?php

namespace App\CatalogSearch\Dto;

use App\CatalogSearch\ApiResource\ProjectMatch;

final class ProjectMatchPage
{
    /**
     * @param list<ProjectMatch> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
