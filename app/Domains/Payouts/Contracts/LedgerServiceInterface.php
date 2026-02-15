<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Models\Payout;
use App\Models\Transaction;

interface LedgerServiceInterface
{
    public function recordPayoutTransaction(Payout $payout): Transaction;

    public function postTransaction(Transaction $transaction): void;
    public function reserve(Transaction $transaction): void;
    public function reverse(Transaction $transaction): void;
}
