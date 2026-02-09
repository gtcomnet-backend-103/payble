<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';

    public function transitions(): array
    {
        return match ($this) {
            self::Pending => [self::Processing, self::Success, self::Failed],
            self::Processing => [self::Success, self::Failed],
            self::Failed, self::Success => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }

    public function is(self|string $status): bool
    {
        $status = $status instanceof self ? $status : self::from($status);

        return $status === $this;
    }
}
