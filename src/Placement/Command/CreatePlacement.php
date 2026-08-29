<?php

namespace App\Placement\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class CreatePlacement implements CommandInterface
{
    public function __construct(
        public readonly Uuid $placementId,
        public readonly Uuid $projectId,
        public readonly Uuid $contextId,
        public readonly Uuid $productId,
        public readonly Uuid $actorId,
    ) {
    }
}
