<?php

namespace App\Visualization\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Visualization\ApiResource\VisualizationOutput;
use App\Visualization\ApiResource\VisualizationRequest;
use App\Visualization\Command\CreateVisualization;
use App\Visualization\Query\GetVisualization;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<VisualizationRequest, VisualizationOutput>
 */
final class CreateVisualizationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): VisualizationOutput
    {
        $visualizationId = Uuid::v7();

        $this->commandBus->dispatch(new CreateVisualization(
            visualizationId: $visualizationId,
            contextId: Uuid::fromString($data->contextId),
            productId: Uuid::fromString($data->productId),
        ));

        return $this->queryBus->ask(new GetVisualization($visualizationId));
    }
}
