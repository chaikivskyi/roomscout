<?php

namespace App\Project\Query;

use App\Api\Bus\QueryInterface;
use App\Project\ApiResource\ProjectOutput;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ProjectOutput>
 */
final class GetProject implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $actorId,
    ) {
    }
}
