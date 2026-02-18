<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Contracts\Recordable;
use App\Models\Transaction;

interface LedgerPostingServiceInterface
{
    public function recordTransaction(Recordable $payout): Transaction;

    public function postTransaction(Transaction $transaction): void;

    public function reserve(Transaction $transaction): void;

    public function reverse(Transaction $transaction): void;
}
