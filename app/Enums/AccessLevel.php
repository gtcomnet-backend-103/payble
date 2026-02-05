<?php

declare(strict_types=1);

namespace App\Enums;

enum AccessLevel: string
{
    case Public = 'public';
    case Secret = 'secret';
}
