<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\Services\LedgerService;
use App\Enums\AccountType;
use App\Models\LedgerAccount;
use App\Models\Provider; // Add import
use App\Models\Transaction;
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
            $providerClearing = $this->ledgerService->getAccount(
                $provider,
                AccountType::PROVIDER_CLEARING,
                $currency,
            );

            // Customer Funds (Liability, owned by Customer)
            $customerSource = $this->ledgerService->getAccount(
                $transaction->paymentIntent->customer,
                AccountType::CUSTOMER_WALLET,
                $currency
            );

            // Platform Revenue (Revenue, owned by Platform/System - null holder)
            $platformRevenue = $this->ledgerService->getAccount(
                null,
                AccountType::PLATFORM_FEE_REVENUE,
                $currency
            );

            // Provider Fees (Expense, owned by Platform/System - null holder)
            $providerFeeExpense = $this->ledgerService->getAccount(
                null,
                AccountType::PROVIDER_FEE_EXPENSE,
                $currency
            );

            // Business Wallet
            $businessWallet = $this->ledgerService->getAccount(
                $business,
                AccountType::BUSINESS_WALLET,
                $currency
            );

            // Get amount from attempt, cus it holds providers amount
            $amount = $transaction->paymentIntent->attempts()->latest()->first()->amount;

            // 1. Record gross inflow from provider
            // DR Provider Clearing / CR Customer Payment Source
            $this->ledgerService->debit($transaction, $providerClearing, $amount);
            $this->ledgerService->credit($transaction, $customerSource, $amount);

            // 2. Apply customer fee
            // DR Customer Payment Source / CR Platform Revenue
            $this->ledgerService->debit($transaction, $customerSource, $customerFee);
            $this->ledgerService->credit($transaction, $platformRevenue, $customerFee);

            // 3. Transfer net amount to business
            // DR Customer Payment Source / CR Business Wallet
            $netToBusiness = $transaction->paymentIntent->amount;
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
