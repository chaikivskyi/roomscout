<?php

namespace App\Project\ApiResource;

final class ProjectSummaryOutput
{
    public function __construct(
        public readonly string $id,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
