<?php

namespace App\Placement\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_placements')]
final class GeneratePlacementImageMessage
{
    public function __construct(
        public readonly string $placementId,
    ) {
    }
}
