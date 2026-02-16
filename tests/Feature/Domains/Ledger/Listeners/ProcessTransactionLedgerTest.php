<?php

declare(strict_types=1);

use App\Domains\Payments\Events\TransactionSuccessful;
use App\Enums\AccountType;
use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Listeners\ProcessTransactionLedger;
use App\Models\Account;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    // Create provider manually since factory might not exist or be different
    $this->provider = Provider::create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'supported_channels' => ['card'],
        'is_active' => true,
    ]);
});

it('processes ledger postings when transaction is successful with account bearer', function () {
    // Arrange
    $paymentIntent = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'amount_paid' => 1000,
        'currency' => Currency::NGN,
        'status' => PaymentStatus::Success,
        'reference' => 'REF_TX_123',
    ]);
    $transaction = $paymentIntent->transaction;
    $transaction->update(['status' => TransactionStatus::Success]);

    $event = new TransactionSuccessful(
        transaction: $transaction,
        provider: $this->provider,
        bearer: FeeBearer::ACCOUNT,
        totalFee: 50,
        providerFee: 10
    );

    // Act
    $listener = app(ProcessTransactionLedger::class);
    $listener->handle($event);

    // Assert
    // 1. Provider Clearing Account (990 Debit)
    $providerAccount = Account::where('type', AccountType::PROVIDER_CLEARING)
        ->where('holder_id', $this->provider->id)
        ->first();

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $providerAccount->id,
        'amount' => 1000,
        'direction' => 'debit',
        'transaction_id' => $transaction->id,
    ]);

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $providerAccount->id,
        'amount' => 10,
        'direction' => 'credit',
        'transaction_id' => $transaction->id,
    ]);

    // 2. Provider Fee Expense (10 Debit)
    $providerFeeAccount = Account::where('type', AccountType::PROVIDER_FEE_EXPENSE)
        ->where('holder_id', $this->provider->id)
        ->first();

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $providerFeeAccount->id,
        'amount' => 10,
        'direction' => 'debit',
        'transaction_id' => $transaction->id,
    ]);

    // 3. Platform Revenue (50 Credit)
    $platformAccount = Account::where('type', AccountType::PLATFORM_FEE_REVENUE)->first();

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $platformAccount->id,
        'amount' => 50,
        'direction' => 'credit',
        'transaction_id' => $transaction->id,
    ]);

    // 4. Business Wallet (950 Credit)
    $businessAccount = Account::where('type', AccountType::BUSINESS_WALLET)
        ->where('holder_id', $this->business->id)
        ->first();

    $this->assertDatabaseHas('ledger_entries', [
        'ledger_account_id' => $businessAccount->id,
        'amount' => 950,
        'direction' => 'credit',
        'transaction_id' => $transaction->id,
    ]);
});
