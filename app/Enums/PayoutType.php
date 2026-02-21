<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutType: string
{
    case Payout = 'payout';
    case Transfer = 'transfer';
}
