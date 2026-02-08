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
        $gross = $transaction->gross_amount; // Total paid by customer (e.g., 10500)

        // Retrieve accounts
        $providerReceivable = $this->ledgerService->providerReceivable($provider, $currency);
        $platformRevenue = $this->ledgerService->platformRevenue($currency);
        $providerFeeExpense = $this->ledgerService->providerFee($provider, $currency);
        $businessWallet = $this->ledgerService->businessReceivable($transaction->business, $currency);

        /*
        |--------------------------------------------------------------------------
        | Calculations
        |--------------------------------------------------------------------------
        | Net Provider: What is actually sitting in Gateway (Gross - Provider Fee)
        | Platform Share: Total income for you (Business Commission + Customer Surcharge)
        | Merchant Share: What the business gets to keep
        */
        $netInProviderAccount = $gross - $providerFee;
        $totalPlatformRevenue = $businessFee + $customerFee;
        $merchantNet = $gross - $totalPlatformRevenue;

        $entries = [];

        // 1. Where is the money? (Assets / Expenses - Debits)
        // Record the actual net amount held by the provider
        $entries[] = LedgerEntryDTO::debit($providerReceivable, $netInProviderAccount);

        // Record the cost of processing as an expense
        if ($providerFee > 0) {
            $entries[] = LedgerEntryDTO::debit($providerFeeExpense, $providerFee);
        }

        // 2. Who owns the money? (Revenue / Liabilities - Credits)
        // Record your platform's earnings
        $entries[] = LedgerEntryDTO::credit($platformRevenue, $totalPlatformRevenue);

        // Record the debt owed to the merchant
        $entries[] = LedgerEntryDTO::credit($businessWallet, $merchantNet);

        // Final check: (netInProvider + providerFee) - (platformRevenue + businessWallet) === 0
        Ledger::transaction($transaction)->entries($entries);
    }
}
