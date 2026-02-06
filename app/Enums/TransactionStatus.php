<?php

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';

    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Success, self::Failed],
            self::Failed, self::Success => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function is(TransactionStatus | string $status): bool
    {
        $status = $status instanceof TransactionStatus ? $status : TransactionStatus::from($status);

        return $status === $this;
    }
}
