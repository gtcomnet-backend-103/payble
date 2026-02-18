<?php

namespace App\Enums;

enum FeeChannel: string
{
    case CARD = 'card';
    case BANK_TRANSFER = 'bank_transfer';
    case Payout = 'payout';
}
