<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

final class CreateDoubleEntry
{
    public function execute(
        Transaction $transaction,
        LedgerAccount $debitAccount,
        LedgerAccount $creditAccount,
        int $amount,
        ?string $reference = null
    ): void {
        DB::transaction(function () use ($transaction, $debitAccount, $creditAccount, $amount, $reference) {
            // Debit Entry (Positive in Assets/Expenses, Negative in Liability/Equity/Revenue)
            // For simplicity in this demo, we'll use a standard convention:
            // Debit increases Assets/Expenses, Credit increases Liabilities/Equity/Revenue.
            // But we will use the 'direction' field explicitly.

            LedgerEntry::create([
                'ledger_account_id' => $debitAccount->id,
                'transaction_id' => $transaction->id,
                'reference' => $reference,
                'amount' => $amount,
                'direction' => 'debit',
            ]);

            LedgerEntry::create([
                'ledger_account_id' => $creditAccount->id,
                'transaction_id' => $transaction->id,
                'reference' => $reference,
                'amount' => -$amount, // Negative for balancing the credit
                'direction' => 'credit',
            ]);
        });
    }
}
