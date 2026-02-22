<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Payout;
use App\Models\Transaction;
use RuntimeException;

final class RecordAdvanceSettlementPostings
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

        $currency = $transaction->currency->value;
        $settlementAmount = $payout->amount; // This is the amount of debt we are clearing
        $payoutFee = $transaction->fee;
        $mode = $transaction->mode;

        // Retrieve accounts
        $receivable = $this->ledgerService->receivable($payout->business, $currency, $mode);
        $advanceAccount = $this->ledgerService->advance($payout->business, $currency, $mode);
        $revenue = $this->ledgerService->platformRevenue($currency, $mode);

        $entries = [
            // Use business earnings to pay back the advance
            LedgerEntryDTO::debit($receivable, $settlementAmount + $payoutFee),

            // Clear the advance debt
            LedgerEntryDTO::credit($advanceAccount, $settlementAmount),

            // Platform earns the payout fee
            LedgerEntryDTO::credit($revenue, $payoutFee),
        ];

        Ledger::transaction($transaction)->name('settlement')->entries($entries);
    }
}
