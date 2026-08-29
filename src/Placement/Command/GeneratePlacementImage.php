<?php

namespace App\Placement\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_placements')]
final class GeneratePlacementImage implements CommandInterface
{
    public function __construct(
        public readonly string $placementId,
    ) {
    }
}
