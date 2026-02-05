<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Provider;
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
        // Idempotency: skip if ledger postings already exist for this transaction
        if ($transaction->ledgerEntries()->exists()) {
            return;
        }

        DB::transaction(function () use ($transaction, $provider, $customerFee, $businessFee, $providerFee) {
            $business = $transaction->business;
            $currency = $transaction->currency->value;

            // Resolve Accounts using domain methods
            $providerClearing = $this->ledgerService->providerClearing($provider, $currency);
            $customerSource = $this->ledgerService->customerWallet($transaction->paymentIntent->customer, $currency);
            $platformRevenue = $this->ledgerService->platformRevenue($currency);
            $providerFeeExpense = $this->ledgerService->providerFeeExpense($currency);
            $businessWallet = $this->ledgerService->businessWallet($business, $currency);

            // Get amount from attempt, cus it holds providers amount
            $amount = $transaction->paymentIntent->attempts()->latest()->first()->amount;

            // 1. Record gross inflow from provider
            // Transfer: Provider Clearing → Customer Payment Source
            $this->ledgerService->transfer($transaction, $providerClearing, $customerSource, $amount);

            // 2. Apply customer fee
            // Transfer: Customer Payment Source → Platform Revenue
            $this->ledgerService->transfer($transaction, $customerSource, $platformRevenue, $customerFee);

            // 3. Transfer net amount to business
            // Transfer: Customer Payment Source → Business Wallet
            $netToBusiness = $transaction->paymentIntent->amount;
            $this->ledgerService->transfer($transaction, $customerSource, $businessWallet, $netToBusiness);

            // 4. Apply business fee
            // Transfer: Business Wallet → Platform Revenue
            $this->ledgerService->transfer($transaction, $businessWallet, $platformRevenue, $businessFee);

            // 5. Record provider fee (expense)
            // Transfer: Provider Clearing → Provider Fee Expense
            $this->ledgerService->transfer($transaction, $providerClearing, $providerFeeExpense, $providerFee);
        });
    }
}
