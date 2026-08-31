<?php

namespace App\Project\Dto;

use App\Project\ApiResource\ProjectListItemOutput;

final class ProjectPage
{
    /**
     * @param list<ProjectListItemOutput> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly int $total,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
