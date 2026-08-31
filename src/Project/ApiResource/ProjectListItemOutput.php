<?php

namespace App\Project\ApiResource;

final class ProjectListItemOutput
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $prompt,
        public readonly ?string $imageUrl,
        public readonly \DateTimeImmutable $createdAt,
    ) {
    }
}
