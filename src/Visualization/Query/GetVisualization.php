<?php

namespace App\Visualization\Query;

use App\Api\Bus\QueryInterface;
use App\Visualization\ApiResource\VisualizationOutput;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<VisualizationOutput>
 */
final class GetVisualization implements QueryInterface
{
    public function __construct(
        public readonly Uuid $visualizationId,
    ) {
    }
}
