<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Facades;

use App\Domains\Ledger\Services\LedgerService;
use Illuminate\Support\Facades\Facade;

/**
 * @method static \App\Domains\Ledger\Services\LedgerTransactionManager transaction(\App\Models\Transaction $transaction)
 * @method static void entries(array $entries)
 *
 * @see LedgerService
 */
final class Ledger extends Facade
{
    public static function getFacadeAccessor(): string
    {
        return LedgerService::class;
    }
}
