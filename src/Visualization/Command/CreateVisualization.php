<?php

namespace App\Visualization\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Uid\Uuid;

final class CreateVisualization implements CommandInterface
{
    public function __construct(
        public readonly Uuid $visualizationId,
        public readonly Uuid $contextId,
        public readonly Uuid $productId,
    ) {
    }
}
