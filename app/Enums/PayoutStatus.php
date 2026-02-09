<?php

declare(strict_types=1);

namespace App\Enums;

enum PayoutStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
    case Reversed = 'reversed';

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
            self::Pending => [self::Processing, self::Completed, self::Failed],
            self::Processing => [self::Completed, self::Failed],
            self::Completed, self::Failed, self::Reversed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->transitions(), true);
    }
}
