<?php

namespace App\Placement\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Api\Bus\QueryBusInterface;
use App\Api\Security\ActorProviderInterface;
use App\Api\State\UriVariables;
use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Exception\PlacementNotFound;
use App\Placement\Query\GetPlacement;
use App\Project\Exception\ProjectNotFound;

/**
 * @implements ProviderInterface<PlacementOutput>
 */
final class PlacementItemProvider implements ProviderInterface
{
    public function __construct(
        private readonly ActorProviderInterface $actor,
        private readonly QueryBusInterface $queryBus,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): PlacementOutput
    {
        $projectId = UriVariables::uuid($uriVariables['projectId'] ?? null) ?? throw new ProjectNotFound();
        $placementId = UriVariables::uuid($uriVariables['placementId'] ?? null) ?? throw new PlacementNotFound();

        return $this->queryBus->ask(new GetPlacement($projectId, $placementId, $this->actor->requireCurrentId()));
    }
}
