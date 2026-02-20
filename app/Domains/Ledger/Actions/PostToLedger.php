<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Transaction;

final readonly class PostToLedger
{
    public function __construct(
        private LedgerService $ledgerService
    ) {}

    /**
     * Reserve funds for a payout (Available → Reserved).
     */
    public function execute(Transaction $transaction): void
    {
        $business = $transaction->business;
        $amount = $transaction->amount;
        $currency = $transaction->currency;
        $mode = $transaction->mode;

        $businessAvailable = $this->ledgerService->receivable($business, $currency->value, $mode);
        $businessReserved = $this->ledgerService->holding($business, $currency->value, $mode);

        Ledger::transaction($transaction)->name('reserve')->entries([
            LedgerEntry::debit($businessAvailable, $amount),
            LedgerEntry::credit($businessReserved, $amount),
        ]);
    }
}
