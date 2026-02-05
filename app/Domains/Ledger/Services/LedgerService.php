<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Enums\AccountType;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

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

    // Domain-specific account accessors (hide AccountType from business code)

    public function customerWallet(Model $customer, string $currency = 'NGN'): LedgerAccount
    {
        return $this->getAccount($customer, AccountType::CUSTOMER_WALLET, $currency);
    }

    public function businessWallet(Model $business, string $currency = 'NGN'): LedgerAccount
    {
        return $this->getAccount($business, AccountType::BUSINESS_WALLET, $currency);
    }

    public function providerClearing(Model $provider, string $currency = 'NGN'): LedgerAccount
    {
        return $this->getAccount($provider, AccountType::PROVIDER_CLEARING, $currency);
    }

    public function platformRevenue(string $currency = 'NGN'): LedgerAccount
    {
        return $this->getAccount(null, AccountType::PLATFORM_FEE_REVENUE, $currency);
    }

    public function providerFeeExpense(string $currency = 'NGN'): LedgerAccount
    {
        return $this->getAccount(null, AccountType::PROVIDER_FEE_EXPENSE, $currency);
    }

    /**
     * Atomically transfer funds between two accounts.
     * Creates both debit and credit entries as one business action.
     *
     * @param  Transaction  $transaction  The transaction context
     * @param  LedgerAccount  $from  Account to debit (source)
     * @param  LedgerAccount  $to  Account to credit (destination)
     * @param  int  $amount  Amount to transfer (positive value)
     * @return array{debit: LedgerEntry, credit: LedgerEntry}
     */
    public function transfer(Transaction $transaction, LedgerAccount $from, LedgerAccount $to, int $amount): array
    {
        $amount = abs($amount); // Ensure positive

        return DB::transaction(function () use ($from, $to, $amount, $transaction) {
            return [
                'debit' => $this->recordEntry($transaction, $from, -$amount, 'debit'),
                'credit' => $this->recordEntry($transaction, $to, $amount, 'credit'),
            ];
        });
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
