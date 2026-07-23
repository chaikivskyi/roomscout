<?php

namespace App\Project\Enum;

enum ProjectStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
