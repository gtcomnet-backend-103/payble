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
        $grossAmount = $payout->amount;
        $platformFee = $transaction->fee;
        $providerFee = $transaction->provider_fee;
        $mode = $transaction->mode;

        // Retrieve accounts
        $businessReserved = $this->ledgerService->holding($transaction->business, $currency, $mode);
        $providerClearing = $this->ledgerService->providerReceivable($payout->provider, $currency, $mode);
        $expense = $this->ledgerService->providerFee($payout->provider, $currency, $mode);
        $revenue = $this->ledgerService->platformRevenue($currency, $mode);
        $advanceAccount = $this->ledgerService->advance($payout->business, $currency, $mode);

        $currentDebt = $this->ledgerService->getBalance($advanceAccount);
        $settlementAmount = 0;

        // If it's a standard payout and business owes an advance
        if ($payout->type === \App\Enums\PayoutType::Payout && $currentDebt > 0) {
            $settlementAmount = min($grossAmount - $platformFee, $currentDebt);
        }

        $amountToDisburse = ($grossAmount - $platformFee) - $settlementAmount;

        $entries = [
            // 1. Release all holds (Gross amount was reserved)
            LedgerEntryDTO::debit($businessReserved, $grossAmount),

            // 2. Clear Advance Debt (if any)
            ...($settlementAmount > 0 ? [
                LedgerEntryDTO::credit($advanceAccount, $settlementAmount),
            ] : []),

            // 3. Move remaining obligation to provider for bank transfer
            ...($amountToDisburse > 0 ? [
                LedgerEntryDTO::credit($providerClearing, $amountToDisburse),
            ] : []),

            // 4. Provider Fee (Expense) - Kept as is, assuming provider fee is matched by Provider
            LedgerEntryDTO::debit($expense, $providerFee),
            LedgerEntryDTO::credit($providerClearing, $providerFee),

            // 5. Platform Fee (Revenue)
            LedgerEntryDTO::credit($revenue, $platformFee),
        ];

        Ledger::transaction($transaction)->name('finalize')->entries($entries);
    }
}
