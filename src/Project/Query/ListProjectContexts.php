<?php

namespace App\Project\Query;

use App\Api\Bus\QueryInterface;
use App\Project\ApiResource\ProjectContextOutput;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<list<ProjectContextOutput>>
 */
final class ListProjectContexts implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $actorId,
    ) {
    }
}
