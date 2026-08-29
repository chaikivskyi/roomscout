<?php

namespace App\Placement\Query;

use App\Api\Bus\QueryInterface;
use App\Placement\ApiResource\PlacementOutput;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<PlacementOutput>
 */
final class GetPlacement implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $placementId,
        public readonly Uuid $actorId,
    ) {
    }
}
