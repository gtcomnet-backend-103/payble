<?php

namespace App\Domains\Ledger\Actions;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\Provider; // Add import
use App\Domains\Ledger\Services\LedgerService;
use Illuminate\Support\Facades\DB;

final class RecordPaymentLedgerPostings
{
    public function __construct(
        private readonly LedgerService $ledgerService
    ) {}

    public function execute(
        Transaction $transaction,
        Provider $provider,
        int $customerFee,
        int $businessFee,
        int $providerFee
    ): void {
        DB::transaction(function () use ($transaction, $provider, $customerFee, $businessFee, $providerFee) {
            $business = $transaction->business;
            $currency = $transaction->currency->value;

            // Resolve Accounts
            // Provider Clearing (Asset, owned by Provider)
            $providerClearing = $this->ledgerService->getOrCreateAccount(
                $provider,
                "{$provider->identifier}_clearing",
                'asset',
                "{$provider->name} Clearing",
                $currency
            );

            // Customer Funds (Liability, owned by Customer)
            $customerSource = $this->ledgerService->getOrCreateAccount(
                $transaction->paymentIntent->customer,
                'customer_funds',
                'liability',
                'Customer Funds',
                $currency
            );

            // Platform Revenue (Revenue, owned by Platform/System - null holder)
            $platformRevenue = $this->ledgerService->getOrCreateAccount(
                null,
                'platform_revenue',
                'revenue',
                'Platform Revenue',
                $currency
            );

            // Provider Fees (Expense, owned by Platform/System - null holder)
            $providerFeeExpense = $this->ledgerService->getOrCreateAccount(
                null,
                'provider_fees',
                'expense',
                'Provider Fee Expense',
                $currency
            );

            // Business Wallet
            $businessWallet = LedgerAccount::where('holder_id', $business->id)
                ->where('holder_type', $business->getMorphClass())
                ->where('slug', $business->id . '_wallet')
                ->firstOrFail();

            // 1. Record gross inflow from provider
            // DR Provider Clearing / CR Customer Payment Source
            $this->ledgerService->debit($transaction, $providerClearing, $transaction->amount);
            $this->ledgerService->credit($transaction, $customerSource, $transaction->amount);

            // 2. Apply customer fee
            // DR Customer Payment Source / CR Platform Revenue
            $this->ledgerService->debit($transaction, $customerSource, $customerFee);
            $this->ledgerService->credit($transaction, $platformRevenue, $customerFee);

            // 3. Transfer net amount to business
            // DR Customer Payment Source / CR Business Wallet
            $netToBusiness = $transaction->amount - $customerFee;
            $this->ledgerService->debit($transaction, $customerSource, $netToBusiness);
            $this->ledgerService->credit($transaction, $businessWallet, $netToBusiness);

            // 4. Apply business fee
            // DR Business Wallet / CR Platform Revenue
            $this->ledgerService->debit($transaction, $businessWallet, $businessFee);
            $this->ledgerService->credit($transaction, $platformRevenue, $businessFee);

            // 5. Record provider fee (expense)
            // DR Provider Fee Expense / CR Provider Clearing
            $this->ledgerService->debit($transaction, $providerFeeExpense, $providerFee);
            $this->ledgerService->credit($transaction, $providerClearing, $providerFee);
        });
    }
}
