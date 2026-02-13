<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Models\Payout;
use App\Models\Transaction;

interface LedgerInterface
{
    public function recordPayoutTransaction(Payout $payout): void;

    public function postTransaction(Transaction $transaction);
}
