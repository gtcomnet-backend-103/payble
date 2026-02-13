<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Domains\Ledger\DataTransferObjects\TransactionData;

/**
 * Interface for models that can be recorded as transactions.
 */
interface Recordable
{
    public function toTransactionData(): TransactionData;
}
