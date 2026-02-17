<?php

declare(strict_types=1);

namespace App\Domains\Ledger\Actions;

use App\Domains\Ledger\DataTransferObjects\LedgerEntry as LedgerEntryDTO;
use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Ledger\Services\LedgerService;
use App\Models\Provider;
use App\Models\Transaction;

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
        $currency = $transaction->currency->value;
        $gross = $transaction->amount;

        // Retrieve accounts
        $providerClearing = $this->ledgerService->providerReceivable($provider, $currency, $transaction->mode);
        $platformRevenue = $this->ledgerService->platformRevenue($currency, $transaction->mode);
        $expense = $this->ledgerService->providerFee($provider, $currency, $transaction->mode);
        $businessWallet = $this->ledgerService->businessReceivable($transaction->business, $currency, $transaction->mode);

        /*
        |--------------------------------------------------------------------------
        | Calculations
        |--------------------------------------------------------------------------
        | Net Provider: What is actually sitting in Gateway (Gross - Provider Fee)
        | Platform Share: Total income for you (Business Commission + Customer Surcharge)
        | Merchant Share: What the business gets to keep
        */
        $totalPlatformRevenue = $businessFee + $customerFee;
        $merchantNet = $gross - $totalPlatformRevenue;
        $netReceivableFromProvider = $gross - $providerFee; // What provider will actually settle

        $entries = [
            // 1. Record net receivable from provider (asset)
            LedgerEntryDTO::debit($providerClearing, $netReceivableFromProvider),

            // 2. Record provider fee as expense
            ...($providerFee > 0 ? [
                LedgerEntryDTO::debit($expense, $providerFee),
            ] : []),

            // 3. Record platform revenue (income) - CREDIT
            LedgerEntryDTO::credit($platformRevenue, $totalPlatformRevenue),

            // 4. Record liability to merchant
            LedgerEntryDTO::credit($businessWallet, $merchantNet),
        ];

        // Final check: (netInProvider + providerFee) - (platformRevenue + businessWallet) === 0
        Ledger::transaction($transaction)->entries($entries);
    }
}
