<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Payout;
use App\Models\Transaction;
use RuntimeException;

final class RecordAdvancePostings
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
        $grossAmount = $payout->amount;
        $platformFee = $transaction->fee;
        $netDisbursement = $grossAmount - $platformFee;
        $mode = $transaction->mode;

        // Retrieve accounts
        $advanceAccount = $this->ledgerService->advance($payout->business, $currency, $mode);
        $revenue = $this->ledgerService->platformRevenue($currency, $mode);

        // We credit the platform's cash/settlement account (represented by null holder receivable)
        $platformCash = $this->ledgerService->platformReceivable($currency, $mode);

        $entries = [
            // Business owes the gross amount
            LedgerEntryDTO::debit($advanceAccount, $grossAmount),

            // Platform sends out the net amount
            LedgerEntryDTO::credit($platformCash, $netDisbursement),

            // Platform earns the fee
            LedgerEntryDTO::credit($revenue, $platformFee),
        ];

        Ledger::transaction($transaction)->name('advance')->entries($entries);
    }
}
