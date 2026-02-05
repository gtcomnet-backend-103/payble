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

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Success, self::Failed, self::PendingPin, self::PendingOtp, self::PendingTransfer],
            self::PendingPin => [self::Success, self::Failed, self::PendingOtp],
            self::PendingOtp => [self::Success, self::Failed],
            self::PendingTransfer => [self::Success, self::Failed],
            self::Success, self::Failed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
