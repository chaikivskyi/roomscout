<?php

namespace App\Catalog\Query;

use App\Api\Bus\QueryInterface;
use App\Catalog\ApiResource\CatalogCategory;
use Symfony\Component\Uid\Uuid;

/**
 * @implements QueryInterface<list<CatalogCategory>>
 */
final class ListCategories implements QueryInterface
{
    public function __construct(
        public readonly ?Uuid $parentId = null,
    ) {
    }
}
