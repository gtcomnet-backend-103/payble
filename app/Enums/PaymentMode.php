<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentMode: string
{
    case Test = 'test';
    case Live = 'live';
}
