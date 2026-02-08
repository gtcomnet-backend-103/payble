<?php

declare(strict_types=1);

namespace App\Domains\Ledger\DataTransferObjects;

use App\Enums\EntryDirection;
use App\Models\Account;

final class LedgerEntry
{
    public function __construct(
        public readonly Account $account,
        public readonly int $amount,
        public readonly EntryDirection $direction,
    ) {}

    public static function debit(Account $account, int $amount): self
    {
        return new self($account, $amount, EntryDirection::DEBIT);
    }

    public static function credit(Account $account, int $amount): self
    {
        return new self($account, $amount, EntryDirection::CREDIT);
    }
}
