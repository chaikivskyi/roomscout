<?php

namespace App\Identity\Command;

use App\Api\Bus\CommandInterface;

final class PurgeGuests implements CommandInterface
{
    public function __construct(
        public readonly int $emptyTtlHours = 24,
        public readonly int $staleTtlDays = 30,
        public readonly int $batch = 500,
        public readonly int $maxBatches = 20,
    ) {
    }
}
