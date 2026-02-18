<?php

declare(strict_types=1);

namespace App\Supports\Services;

use App\Contracts\Recordable;
use App\Domains\Ledger\Actions\PostToLedger;
use App\Domains\Ledger\Actions\RecordPayoutLedgerPostings;
use App\Domains\Ledger\Actions\RecordTransaction;
use App\Domains\Ledger\Actions\ReversePayoutLedgerPostings;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Models\Payout;
use App\Models\Transaction;

final class LedgerPayoutPostingService implements LedgerPostingServiceInterface
{
    public function __construct(
        private readonly RecordTransaction $recordTransaction,
        private readonly PostToLedger $reservePostToLedger,
        private readonly RecordPayoutLedgerPostings $recordPayoutLedgerPostings,
        private readonly ReversePayoutLedgerPostings $reversePayoutLedgerPostings,
    ) {}

    public function recordTransaction(Recordable $payout): Transaction
    {
        return $this->recordTransaction->execute($payout);
    }

    public function postTransaction(Transaction $transaction): void
    {
        $this->recordPayoutLedgerPostings->execute($transaction);
    }

    public function reserve(Transaction $transaction): void
    {
        $this->reservePostToLedger->execute($transaction);
    }

    public function reverse(Transaction $transaction): void
    {
        $this->reversePayoutLedgerPostings->execute($transaction);
    }
}
