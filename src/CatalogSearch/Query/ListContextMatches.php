<?php

namespace App\CatalogSearch\Query;

use App\Api\Bus\QueryInterface;
use App\CatalogSearch\Dto\ContextMatchPage;
use App\CatalogSearch\Dto\MatchFilters;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ContextMatchPage>
 */
final class ListContextMatches implements QueryInterface
{
    public function __construct(
        public readonly Uuid $contextId,
        public readonly MatchFilters $filters,
        public readonly int $page,
        public readonly int $limit,
    ) {
    }
}
