<?php

namespace App\Visualization\Enum;

enum VisualizationStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
