<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentStatus: string
{
    case Initiated = 'initiated';
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Reversed = 'reversed';

    public function is(PaymentStatus | string $status): bool
    {
        $status = $status instanceof PaymentStatus ? $status : PaymentStatus::from($status);

        return $status === $this;
    }
}
