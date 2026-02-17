<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Enums\AccountType;
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
        $businessReserved = $this->ledgerService->getAccount($transaction->business, AccountType::BUSINESS_HOLDS, $currency, $mode);
        $providerClearing = $this->ledgerService->providerReceivable($payout->provider, $currency, $mode);
        $expense = $this->ledgerService->providerFee($payout->provider, $currency, $mode);
        $revenue = $this->ledgerService->platformRevenue($currency, $mode);

        $entries = [
            // Debit Provider Clearing (Increase provider clearing/receivable)
            LedgerEntryDTO::debit($providerClearing, $amount),
            LedgerEntryDTO::credit($businessReserved, $amount),

            LedgerEntryDTO::debit($expense, $providerFee),

            // Credit Business Reserved (Decrease reserved balance)

            LedgerEntryDTO::debit($businessReserved, $platformFee),
            LedgerEntryDTO::credit($revenue, $platformFee),
        ];

        Ledger::transaction($transaction)->entries($entries);
    }
}
