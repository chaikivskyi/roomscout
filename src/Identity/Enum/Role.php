<?php

namespace App\Identity\Enum;

enum Role: string
{
    case User = 'ROLE_USER';
    case Admin = 'ROLE_ADMIN';
}
