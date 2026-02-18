<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentChannel: string
{
    case Card = 'card';
    case BankTransfer = 'bank_transfer';

    public function toFeeChannelEnum(): FeeChannel
    {
        return match ($this) {
            self::Card => FeeChannel::CARD,
            self::BankTransfer => FeeChannel::BANK_TRANSFER,
        };
    }
}
