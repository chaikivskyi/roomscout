<?php

namespace App\Visualization\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\Visualization\ApiResource\VisualizationOutput;
use App\Visualization\Query\GetVisualization;

/**
 * @implements ProviderInterface<VisualizationOutput>
 */
final class VisualizationItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): VisualizationOutput
    {
        $visualizationId = UriVariables::uuid($uriVariables['visualizationId'] ?? null);

        return $this->queryBus->ask(new GetVisualization($visualizationId));
    }
}
