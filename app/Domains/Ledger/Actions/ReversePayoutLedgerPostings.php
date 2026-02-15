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
        $mode = $transaction->mode;

        // Retrieve accounts
        $businessWallet = $this->ledgerService->businessReceivable($transaction->business, $currency, $mode);
        $businessReserved = $this->ledgerService->getAccount($transaction->business, AccountType::BUSINESS_HOLDS, $currency, $mode);

        $entries = [
            // Debit Business Wallet (Increase Balance - Refund to available)
            LedgerEntryDTO::debit($businessWallet, $amount),

            // Credit Business Reserved (Decrease reserved balance)
            LedgerEntryDTO::credit($businessReserved, $amount),
        ];

        Ledger::transaction($transaction)->entries($entries);
    }
}
