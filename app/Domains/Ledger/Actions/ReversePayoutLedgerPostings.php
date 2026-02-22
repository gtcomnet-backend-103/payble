<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Payout;
use App\Models\Transaction;
use RuntimeException;

final class ReversePayoutLedgerPostings
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
            // If provider was not selected, we probably didn't post ledger entries yet.
            // But if we did (and then provider call failed), provider should be set.
            throw new RuntimeException('Payout provider must be present to reverse ledger postings.');
        }

        $currency = $transaction->currency->value;
        $amount = $payout->amount;
        $fee = $transaction->fee;
        $mode = $transaction->mode;

        // Retrieve accounts
        if ($payout->type === \App\Enums\PayoutType::Advance) {
            $advanceAccount = $this->ledgerService->advance($payout->business, $currency, $mode);
            $platformCash = $this->ledgerService->platformReceivable($currency, $mode);
            $revenue = $this->ledgerService->platformRevenue($currency, $mode);

            $entries = [
                // Credit Advance (Decrease debt)
                LedgerEntryDTO::credit($advanceAccount, $amount),

                // Debit Platform Cash (Recover funds)
                LedgerEntryDTO::debit($platformCash, $amount - $fee),

                // Debit Revenue (Reverse the fee income)
                LedgerEntryDTO::debit($revenue, $fee),
            ];
        } else {
            $businessWallet = $this->ledgerService->businessReceivable($transaction->business, $currency, $mode);
            $businessReserved = $this->ledgerService->holding($transaction->business, $currency, $mode);

            $entries = [
                // Credit Business Wallet (Refund to available - Increase liability)
                LedgerEntryDTO::credit($businessWallet, $amount),

                // Debit Business Reserved (Decrease reserved liability)
                LedgerEntryDTO::debit($businessReserved, $amount),
            ];
        }

        Ledger::transaction($transaction)->name('reverse')->entries($entries);
    }
}
