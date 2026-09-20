<?php

namespace App\Visualization\Query;

use App\Visualization\ApiResource\VisualizationOutput;
use App\Visualization\Exception\VisualizationNotFound;
use App\Visualization\Repository\ProductVisualizationRepository;
use App\Visualization\Service\VisualizationOutputMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetVisualizationHandler
{
    public function __construct(
        private readonly ProductVisualizationRepository $visualizations,
        private readonly VisualizationOutputMapper $mapper,
    ) {
    }

    public function __invoke(GetVisualization $query): VisualizationOutput
    {
        $visualization = $this->visualizations->find($query->visualizationId) ?? throw new VisualizationNotFound();

        return $this->mapper->map($visualization);
    }
}
