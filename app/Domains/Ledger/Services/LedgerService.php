<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Services;

use App\Enums\AccountType;
use App\Enums\EntryDirection;
use App\Enums\PaymentMode;
use App\Models\Account;
use App\Models\LedgerBatch;
use App\Models\LedgerEntry;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class LedgerService
{
    /**
     * Get or create a ledger account for a holder of a specific type.
     */
    public function getAccount(
        ?Model $holder,
        AccountType $type,
        string $currency = 'NGN',
        PaymentMode $mode = PaymentMode::Live,
        array $metadata = []
    ): Account {
        return Account::firstOrCreate(
            [
                'holder_id' => $holder?->getKey(),
                'holder_type' => $holder?->getMorphClass(),
                'type' => $type,
                'currency' => $currency,
                'mode' => $mode,
            ],
            [
                'metadata' => $metadata,
            ]
        );
    }

    public function customerWallet(Model $customer, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount($customer, AccountType::CUSTOMER_WALLET, $currency, $mode);
    }

    public function businessReceivable(Model $business, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount($business, AccountType::BUSINESS_WALLET, $currency, $mode);
    }

    public function providerReceivable(Model $provider, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount($provider, AccountType::PROVIDER_CLEARING, $currency, $mode);
    }

    public function platformReceivable(string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount(null, AccountType::PLATFORM_CLEARING, $currency, $mode);
    }

    public function platformRevenue(string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount(null, AccountType::PLATFORM_FEE_REVENUE, $currency, $mode);
    }

    public function providerFee(Provider $provider, string $currency, PaymentMode $mode = PaymentMode::Live): Account
    {
        return $this->getAccount($provider, AccountType::PROVIDER_FEE_EXPENSE, $currency, $mode);
    }

    /**
     * Start a fluent ledger transaction.
     */
    public function transaction(Transaction $transaction): LedgerTransactionManager
    {
        return new LedgerTransactionManager($this, $transaction);
    }

    /**
     * Start a new ledger batch for a transaction.
     * Idempotency is guaranteed by unique transaction_id index.
     */
    public function startBatch(Transaction $tx): LedgerBatch
    {
        return LedgerBatch::firstOrCreate([
            'transaction_id' => $tx->id,
        ]);
    }

    /**
     * Post a double-entry movement between two accounts.
     * Must be called within a DB transaction.
     */
    public function post(
        LedgerBatch $batch,
        Transaction $tx,
        Account $debit,
        Account $credit,
        int $amount
    ): void {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($batch, $tx, $debit, $credit, $amount) {
            // 1. Create Debit Entry
            $debitEntry = LedgerEntry::create([
                'ledger_batch_id' => $batch->id,
                'ledger_account_id' => $debit->id,
                'transaction_id' => $tx->id,
                'reference' => $tx->reference,
                'amount' => $amount,
                'direction' => EntryDirection::DEBIT,
            ]);

            // 2. Create Credit Entry
            $creditEntry = LedgerEntry::create([
                'ledger_batch_id' => $batch->id,
                'ledger_account_id' => $credit->id,
                'transaction_id' => $tx->id,
                'reference' => $tx->reference,
                'amount' => $amount,
                'direction' => EntryDirection::CREDIT,
            ]);

            // 3. Increment Snapshots (Atomic SQL)
            $this->incrementSnapshot($debit, $amount, $debitEntry->id);
            $this->incrementSnapshot($credit, -$amount, $creditEntry->id);
        });
    }

    /**
     * Get the current balance of an account from the snapshot table.
     */
    public function getBalance(Account $account): int
    {
        return (int) DB::table('account_balances')
            ->where('ledger_account_id', $account->id)
            ->value('balance') ?? 0;
    }

    /**
     * Perform an atomic balance update using SQL UPSERT.
     * This is highly concurrent and prevent lost updates.
     */
    public function incrementSnapshot(Account $account, int $amount, int $entryId): void
    {
        DB::table('account_balances')->upsert(
            [
                'ledger_account_id' => $account->id,
                'balance' => $amount,
                'last_entry_id' => $entryId,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            ['ledger_account_id'],
            [
                'balance' => DB::raw("balance + {$amount}"),
                'last_entry_id' => $entryId,
                'updated_at' => now(),
            ]
        );
    }

    /**
     * Issue an internal credit to an account (e.g. for testing or manual adjustments).
     * This creates a ledger entry and updates the snapshot.
     */
    public function issueInternalCredit(Account $account, int $amount): void
    {
        if ($amount <= 0) {
            return;
        }

        DB::transaction(function () use ($account, $amount) {
            $batch = LedgerBatch::create([
                'transaction_id' => null, // Internal adjustment
                'metadata' => ['reason' => 'Internal Credit'],
            ]);

            $entry = LedgerEntry::create([
                'ledger_batch_id' => $batch->id,
                'ledger_account_id' => $account->id,
                'transaction_id' => null,
                'reference' => 'INT-' . strtoupper(bin2hex(random_bytes(4))),
                'amount' => $amount,
                'direction' => EntryDirection::CREDIT,
            ]);

            $this->incrementSnapshot($account, $amount, $entry->id);
        });
    }
}
