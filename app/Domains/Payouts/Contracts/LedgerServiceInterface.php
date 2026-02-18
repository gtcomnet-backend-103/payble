<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Enums\PaymentMode;
use App\Models\Account;
use Illuminate\Database\Eloquent\Model;

interface LedgerServiceInterface
{
    public function receivable(Model $model, string $currency, PaymentMode $mode = PaymentMode::Live): Account;

    /**
     * Get the current balance of an account from the snapshot table.
     */
    public function getBalance(Account $account): int;
}
