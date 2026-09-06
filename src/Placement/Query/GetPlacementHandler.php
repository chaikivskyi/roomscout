<?php

namespace App\Placement\Query;

use App\Placement\ApiResource\PlacementOutput;
use App\Placement\Exception\PlacementNotFound;
use App\Placement\Repository\ProductPlacementRepository;
use App\Placement\Service\PlacementOutputMapper;
use App\Project\Service\ProjectFinder;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'query.bus')]
final class GetPlacementHandler
{
    public function __construct(
        private readonly ProjectFinder $projects,
        private readonly ProductPlacementRepository $placements,
        private readonly PlacementOutputMapper $mapper,
    ) {
    }

    public function __invoke(GetPlacement $query): PlacementOutput
    {
        $project = $this->projects->find($query->projectId);

        $placement = $this->placements->findOneForProject($project->getId(), $query->placementId)
            ?? throw new PlacementNotFound();

        return $this->mapper->map($placement);
    }
}
