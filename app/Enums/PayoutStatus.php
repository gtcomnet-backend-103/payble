<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case Draft = 'draft';
    case Complete = 'complete';
    case Unknown = 'unknown';
    case ReconciliationRequired = 'reconciliation_required';

    public function is(self|string $status): bool
    {
        return $this === ($status instanceof self ? $status : self::from($status));
    }

    /**
     * @return array<int, self>
     */
    public function transitions(): array
    {
        return match ($this) {
            self::Draft => [self::Pending],
            self::Pending => [self::Processing, self::Success, self::Failed],
            self::Processing => [self::Success, self::Failed, self::Unknown, self::ReconciliationRequired],
            self::Success, self::Failed, self::Reversed, self::Unknown, self::ReconciliationRequired => [],
            self::Complete => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
