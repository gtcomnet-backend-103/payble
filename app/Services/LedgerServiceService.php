<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Enums\TransactionStatus;
use App\Models\Payout;
use App\Models\Transaction;

final class LedgerServiceService implements LedgerServiceInterface
{
    public function __construct() {}

    public function recordPayoutTransaction(Payout $payout): Transaction
    {
        return $payout->transaction()->create([
            'business_id' => $payout->business_id,
            'reference' => $payout->reference,
            'currency' => $payout->currency,
            'status' => TransactionStatus::Pending,
            'mode' => $payout->mode,
            'metadata' => [],
        ]);

    }

    public function postTransaction(Transaction $transaction): void
    {
        // TODO: Implement postTransaction() method.
    }

    public function reserve(Transaction $transaction)
    {
        // TODO: Implement reserve() method.
    }
}
