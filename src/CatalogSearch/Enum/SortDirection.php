<?php

namespace App\CatalogSearch\Enum;

enum SortDirection: string
{
    case Asc = 'asc';
    case Desc = 'desc';

    /**
     * @return 'ASC'|'DESC'
     */
    public function toOrderKeyword(): string
    {
        return self::Asc === $this ? 'ASC' : 'DESC';
    }
}
