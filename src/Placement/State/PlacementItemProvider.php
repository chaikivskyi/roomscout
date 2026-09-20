<?php

namespace App\Placement\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\State\UriVariables;
use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Query\GetPlacement;

/**
 * @implements ProviderInterface<PlacementOutput>
 */
final class PlacementItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PlacementOutput
    {
        $placementId = UriVariables::uuid($uriVariables['placementId'] ?? null);

        return $this->queryBus->ask(new GetPlacement($placementId));
    }
}
