<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Payout;
use App\Models\Transaction;
use RuntimeException;

final class RecordAdvanceDisbursementPostings
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
            throw new RuntimeException('Provider must be selected before recording disbursement postings.');
        }

        $currency = $transaction->currency->value;
        $amount = $payout->amount;
        $fee = $transaction->fee;
        $netAmount = $amount - $fee;
        $mode = $transaction->mode;

        // Retrieve accounts
        $platformCash = $this->ledgerService->platformReceivable($currency, $mode);
        $providerClearing = $this->ledgerService->providerReceivable($payout->provider, $currency, $mode);

        $entries = [
            // Move obligation from Platform Cash to Provider Clearing
            LedgerEntryDTO::debit($platformCash, $netAmount),
            LedgerEntryDTO::credit($providerClearing, $netAmount),
        ];

        Ledger::transaction($transaction)->name('disburse')->entries($entries);
    }
}
