<?php

namespace App\Project\Enum;

enum ProjectContextStatus: string
{
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
