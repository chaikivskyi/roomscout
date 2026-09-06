<?php

namespace App\Placement\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Api\Bus\CommandBusInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\Placement\ApiResource\PlacementOutput;
use App\Placement\ApiResource\PlacementRequest;
use App\Placement\Command\CreatePlacement;
use App\Placement\Query\GetPlacement;
use Symfony\Component\Uid\Uuid;

/**
 * @implements ProcessorInterface<PlacementRequest, PlacementOutput>
 */
final class CreatePlacementProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly CommandBusInterface $commandBus,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): PlacementOutput
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null);
        $placementId = Uuid::v7();

        $this->commandBus->dispatch(new CreatePlacement(
            placementId: $placementId,
            projectId: $projectId,
            contextId: Uuid::fromString($data->contextId),
            productId: Uuid::fromString($data->productId),
        ));

        return $this->queryBus->ask(new GetPlacement($projectId, $placementId));
    }
}
