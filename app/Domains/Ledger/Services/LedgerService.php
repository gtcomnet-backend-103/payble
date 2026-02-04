<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Enums\AccountType;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class LedgerService
{
    public function getAccount(
        ?Model $holder,
        AccountType $type,
        string $currency = 'NGN',
        array $metadata = []
    ): LedgerAccount {
        return LedgerAccount::firstOrCreate(
            [
                'holder_id' => $holder?->getKey(),
                'holder_type' => $holder?->getMorphClass(),
                'type' => $type,
                'currency' => $currency,
            ],
            [
                'metadata' => $metadata,
            ]
        );
    }

    public function debit(Transaction $transaction, LedgerAccount $account, int $amount): LedgerEntry
    {
        return $this->recordEntry($transaction, $account, -abs($amount), 'debit');
    }

    public function credit(Transaction $transaction, LedgerAccount $account, int $amount): LedgerEntry
    {
        return $this->recordEntry($transaction, $account, abs($amount), 'credit');
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
