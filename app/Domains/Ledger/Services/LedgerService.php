<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;

final class LedgerService
{
    public function getOrCreateAccount(
        ?Model $holder,
        string $slug,
        string $type,
        string $name,
        string $currency = 'NGN',
        ?array $metadata = null
    ): LedgerAccount {
        $holderId = $holder?->getKey();
        $holderType = $holder?->getMorphClass();
        $prefix = $holder ? $holderId : 'system';

        return LedgerAccount::firstOrCreate(
            ['slug' => "{$prefix}_{$slug}"],
            [
                'name' => $name,
                'type' => $type,
                'holder_id' => $holderId,
                'holder_type' => $holderType,
                'currency' => $currency,
                'metadata' => $metadata,
            ]
        );
    }

    public function debit(Transaction $transaction, LedgerAccount $account, int $amount): LedgerEntry
    {
        return $this->recordEntry($transaction, $account, abs($amount), 'debit');
    }

    public function credit(Transaction $transaction, LedgerAccount $account, int $amount): LedgerEntry
    {
        return $this->recordEntry($transaction, $account, -abs($amount), 'credit');
    }

    private function recordEntry(
        Transaction $transaction,
        LedgerAccount $account,
        int $amount,
        string $direction
    ): LedgerEntry {
        return LedgerEntry::create([
            'ledger_account_id' => $account->id,
            'transaction_id' => $transaction->id,
            'reference' => $transaction->reference,
            'amount' => $amount,
            'direction' => $direction,
        ]);
    }
}
