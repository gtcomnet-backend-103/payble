<?php

declare(strict_types=1);

namespace App\Enums;

enum FeeBearer: string
{
    case Merchant = 'merchant';
    case Customer = 'customer';
    case Split = 'split';
}
