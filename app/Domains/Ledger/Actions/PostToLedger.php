<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\Services\LedgerService;
use App\Enums\AccountType;
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

        $businessAvailable = $this->ledgerService->businessReceivable($business, $currency->value, $mode);
        $businessReserved = $this->ledgerService->getAccount(
            $business,
            AccountType::BUSINESS_HOLDS,
            $currency->value,
            $mode
        );

        $batch = $this->ledgerService->startBatch($transaction, 'payout_reservation');
        $this->ledgerService->post(
            $batch,
            $transaction,
            $businessReserved,    // DEBIT reserved (increase)
            $businessAvailable,   // CREDIT available (decrease)
            $amount
        );
    }
}
