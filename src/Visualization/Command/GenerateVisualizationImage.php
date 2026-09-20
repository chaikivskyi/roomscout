<?php

namespace App\Visualization\Command;

use App\Api\Bus\CommandInterface;
use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async_visualizations')]
final class GenerateVisualizationImage implements CommandInterface
{
    public function __construct(
        public readonly string $visualizationId,
    ) {
    }
}
