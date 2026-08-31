<?php

namespace App\Project\Query;

use App\Api\Bus\QueryInterface;
use App\Project\Dto\ProjectPage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ProjectPage>
 */
final class ListProjects implements QueryInterface
{
    public function __construct(
        public readonly Uuid $actorId,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
