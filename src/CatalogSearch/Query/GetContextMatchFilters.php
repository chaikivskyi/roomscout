<?php

namespace App\CatalogSearch\Query;

use App\Api\Bus\QueryInterface;
use App\CatalogSearch\ApiResource\ProjectMatchFilters;
use App\CatalogSearch\Dto\MatchFilters;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ProjectMatchFilters>
 */
final class GetContextMatchFilters implements QueryInterface
{
    public function __construct(
        public readonly Uuid $projectId,
        public readonly Uuid $contextId,
        public readonly Uuid $actorId,
        public readonly MatchFilters $filters,
    ) {
    }
}
