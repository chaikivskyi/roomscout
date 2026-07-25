<?php

namespace App\Project\ApiResource;

final class ProjectOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $prompt,
        public readonly string $status,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
