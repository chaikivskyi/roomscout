<?php

namespace App\CatalogSearch\Query;

use App\Api\Bus\QueryInterface;
use App\CatalogSearch\Dto\MatchFilters;
use App\CatalogSearch\Dto\ProjectMatchPage;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ProjectMatchPage>
 */
final class ListContextMatches implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $contextId,
        public readonly Uuid $actorId,
        public readonly MatchFilters $filters,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
