<?php

declare(strict_types=1);

namespace App\Supports\Services;

use App\Domains\Ledger\Actions\PostToLedger;
use App\Domains\Ledger\Actions\RecordPayoutLedgerPostings;
use App\Domains\Ledger\Actions\RecordTransaction;
use App\Domains\Ledger\Actions\ReversePayoutLedgerPostings;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Models\Payout;
use App\Models\Transaction;

final class LedgerPayoutService implements LedgerServiceInterface
{
    public function __construct(
        private RecordTransaction $recordTransaction,
        private PostToLedger $reservePostToLedger,
        private RecordPayoutLedgerPostings $recordPayoutLedgerPostings,
        private ReversePayoutLedgerPostings $reversePayoutLedgerPostings,
    ) {}

    public function recordPayoutTransaction(Payout $payout): Transaction
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
