<?php

declare(strict_types=1);

namespace App\Supports\Services;

use App\Domains\Ledger\Services\LedgerService as Ledger;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Enums\PaymentMode;
use App\Models\Account;
use Illuminate\Database\Eloquent\Model;

final class LedgerService implements LedgerServiceInterface
{
    public function __construct(private readonly Ledger $ledgerService) {}

    public function receivable(Model $model, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->ledgerService->receivable($model, $currency, $mode);
    }

    public function advance(Model $model, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->ledgerService->advance($model, $currency, $mode);
    }

    public function getBalance(Account $account): int
    {
        return $this->ledgerService->getBalance($account);
    }
}
