<?php

declare(strict_types=1);

namespace App\Services;

use App\Domains\Payouts\Contracts\LedgerInterface;
use App\Enums\TransactionStatus;
use App\Models\Payout;
use App\Models\Transaction;

final class LedgerService implements LedgerInterface
{
    public function __construct() {}

    public function recordPayoutTransaction(Payout $payout): void
    {
        $transaction = $payout->transaction()->create([
            'business_id' => $payout->business_id,
            'reference' => $payout->reference,
            'currency' => $payout->currency,
            'status' => TransactionStatus::Pending,
            'mode' => $payout->mode,
            'metadata' => [],
        ]);

    }

    public function postTransaction(Transaction $transaction)
    {
        // TODO: Implement postTransaction() method.
    }
}
