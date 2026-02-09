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
        $mode = $transaction->mode;

        // Retrieve accounts
        $businessWallet = $this->ledgerService->businessReceivable($transaction->business, $currency, $mode);
        $providerClearing = $this->ledgerService->providerReceivable($payout->provider, $currency, $mode);

        $entries = [
            // Debit Business Wallet (Decrease Balance)
            LedgerEntryDTO::debit($businessWallet, $amount),

            // Credit Provider Clearing (Money leaving system via provider)
            LedgerEntryDTO::credit($providerClearing, $amount),
        ];

        Ledger::transaction($transaction)->entries($entries);
    }
}
