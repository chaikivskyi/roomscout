<?php

namespace App\CatalogSearch\Query;

use App\Api\Bus\QueryInterface;
use App\CatalogSearch\ApiResource\ContextMatchFilters;
use App\CatalogSearch\Dto\MatchFilters;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<ContextMatchFilters>
 */
final class GetContextMatchFilters implements QueryInterface
{
    public function __construct(
        public readonly Uuid $contextId,
        public readonly MatchFilters $filters,
    ) {
    }
}
