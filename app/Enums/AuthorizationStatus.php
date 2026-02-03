<?php

declare(strict_types=1);

namespace App\Enums;

enum AuthorizationStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case PendingPin = 'pending_pin';
    case PendingOtp = 'pending_otp';
    case PendingTransfer = 'pending_transfer';


    public function is(AuthorizationStatus | string $status): bool
    {
        $status = $status instanceof AuthorizationStatus ? $status : AuthorizationStatus::from($status);

        return $status === $this;
    }
}
