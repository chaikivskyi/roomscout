<?php

namespace App\Project\Query;

use App\Api\Bus\QueryInterface;
use App\Project\ApiResource\ProjectContextOutput;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ProjectContextOutput>
 */
final class GetProjectContext implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $contextId,
        public readonly Uuid $actorId,
    ) {
    }
}
