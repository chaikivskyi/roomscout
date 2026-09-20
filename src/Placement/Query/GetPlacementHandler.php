<?php

namespace App\Placement\Query;

use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Exception\PlacementNotFound;
use App\Placement\Repository\ProductPlacementRepository;
use App\Placement\Service\PlacementOutputMapper;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetPlacementHandler
{
    public function __construct(
        private readonly ProductPlacementRepository $placements,
        private readonly PlacementOutputMapper $mapper,
    ) {
    }

    public function __invoke(GetPlacement $query): PlacementOutput
    {
        $placement = $this->placements->find($query->placementId) ?? throw new PlacementNotFound();

        return $this->mapper->map($placement);
    }
}
