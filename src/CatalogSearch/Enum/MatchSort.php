<?php

namespace App\CatalogSearch\Enum;

enum MatchSort: string
{
    case Score = 'score';
    case Price = 'price';
}
