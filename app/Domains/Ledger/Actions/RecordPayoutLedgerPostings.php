<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Payout;
use App\Models\Transaction;
use RuntimeException;

final class RecordPayoutLedgerPostings
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(Transaction $transaction): void
    {
        /** @var Payout $payout */
        $payout = $transaction->source;

        if (! $payout instanceof Payout) {
            throw new RuntimeException('Transaction source is not a Payout.');
        }

        if (! $payout->provider) {
            throw new RuntimeException('Payout provider must be selected before recording ledger postings.');
        }

        $currency = $transaction->currency->value;
        $amount = $payout->amount;
        $platformFee = $transaction->fee;
        $providerFee = $transaction->provider_fee;
        $mode = $transaction->mode;

        // Retrieve accounts
        $businessReserved = $this->ledgerService->holding($transaction->business, $currency, $mode);
        $providerClearing = $this->ledgerService->providerReceivable($payout->provider, $currency, $mode);
        $expense = $this->ledgerService->providerFee($payout->provider, $currency, $mode);
        $revenue = $this->ledgerService->platformRevenue($currency, $mode);

        $entries = [
            // Release holds (The full gross amount was reserved)
            LedgerEntryDTO::debit($businessReserved, $amount),

            // Move obligation to provider (Net amount)
            LedgerEntryDTO::credit($providerClearing, $amount - $platformFee),

            // Provider Fee (Expense) - Kept as is, assuming provider fee is matched by Provider
            LedgerEntryDTO::debit($expense, $providerFee),
            LedgerEntryDTO::credit($providerClearing, $providerFee),

            // Platform Fee (Revenue) - Already deducted from business via hold release
            LedgerEntryDTO::credit($revenue, $platformFee),
        ];

        Ledger::transaction($transaction)->name('finalize')->entries($entries);
    }
}
