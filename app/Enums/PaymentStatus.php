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

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Initiated => [self::Pending, self::Processing, self::Success, self::Failed],
            self::Pending => [self::Processing, self::Success, self::Failed],
            self::Processing => [self::Success, self::Failed],
            self::Success => [self::Reversed],
            self::Failed, self::Reversed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
