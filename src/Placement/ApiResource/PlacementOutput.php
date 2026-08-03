<?php

namespace App\Placement\ApiResource;

final class PlacementOutput
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $contextId,
        public readonly ?string $productId,
        public readonly string $prompt,
        public readonly ?string $resultVersionId,
        public readonly ?string $resultImageUrl,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {
    }
}
