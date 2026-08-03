<?php

namespace App\Placement\Enum;

enum PlacementStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
