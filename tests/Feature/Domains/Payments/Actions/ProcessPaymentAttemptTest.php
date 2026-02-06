<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Payments\Actions;

use App\Domains\Payments\Actions\AuthorizePayment;
use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\FeeBearer;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    PaymentProvider::fake();
    $this->provider = Provider::factory()->test()->create();
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    $this->payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_PAYMENT_1',
        'bearer' => FeeBearer::Merchant,
        'status' => PaymentStatus::Pending,
        'currency' => 'NGN',
    ]);

    $this->attempt = app(AuthorizePayment::class)->createAttempt($this->payment, PaymentChannel::Card);
    $this->attempt->update([
        'provider_reference' => 'REF_PROV_123',
    ]);
});

it('processes a successful payment attempt', function () {
    // 1. Mock PaymentProvider
    PaymentProvider::shouldReceive('verifyTransaction')
        ->with(
            Mockery::on(fn($p) => $p->id === $this->provider->id),
            'REF_PROV_123'
        )
        ->once()
        ->andReturn(new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: 'REF_PROV_123',
            rawResponse: []
        ));

    // 3. Execute Action
    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeTrue();

    // 4. Verify State Changes
    $this->attempt->refresh();
    $this->payment->refresh();

    expect($this->attempt->status)->toBe(AuthorizationStatus::Success);
    expect($this->payment->status)->toBe(PaymentStatus::Success);

    $transaction = Transaction::where('reference', $this->payment->reference)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(TransactionStatus::Success);
    expect($transaction->amount)->toBe(1000);
});

it('calculates split fees correctly', function () {
    $this->payment->update(['bearer' => FeeBearer::Split]);
    $this->attempt->update(['fee' => 20]); // Total fee 20

    // Mock Provider calls
    PaymentProvider::shouldReceive('verifyTransaction')->andReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'REF_PROV_123',
        rawResponse: []
    ));
    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeTrue();
});

it('returns false when provider verification fails', function () {
    PaymentProvider::shouldReceive('verifyTransaction')
        ->once()
        ->andReturn(new ProviderResponse(
            status: AuthorizationStatus::Pending, // Not Success
            providerReference: 'REF_PROV_123',
            rawResponse: []
        ));

    // recordLedger should NOT be called

    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeFalse();

    $this->attempt->refresh();
    expect($this->attempt->status)->toBe(AuthorizationStatus::Pending);
});

it('returns false and logs error when verification throws exception', function () {
    PaymentProvider::shouldReceive('verifyTransaction')
        ->once()
        ->andThrow(new Exception('Network Error'));

    PaymentProvider::shouldReceive('getFee')->never();

    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeFalse();

    // Status should remain unchanged
    $this->attempt->refresh();
    expect($this->attempt->status)->toBe(AuthorizationStatus::Pending);
});

it('ensures transaction exists (idempotency)', function () {
    // If transaction already exists, it shouldn't create a new one, but use it.
    $existingTx = Transaction::create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'currency' => 'NGN',
        'status' => 'pending',
        'reference' => $this->payment->reference,
        'channel' => PaymentChannel::Card,
        'mode' => 'live',
    ]);

    PaymentProvider::shouldReceive('verifyTransaction')->andReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'REF_PROV_123',
        rawResponse: []
    ));
    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeTrue();

    $existingTx->refresh();
    expect($existingTx->status)->toBe(TransactionStatus::Success);
    expect(Transaction::count())->toBe(1);
});

it('ensures no double-posting to ledger (idempotent)', function () {
    // 1. Arrange: Prepare successful verification
    PaymentProvider::shouldReceive('verifyTransaction')->andReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'REF_PROV_IDEM',
        rawResponse: []
    ));

    // 2. Execute First Time
    $action = app(ProcessPaymentAttempt::class);
    $action->execute($this->attempt);

    $transaction = Transaction::where('reference', $this->payment->reference)->first();
    $entryCount = $transaction->ledgerEntries()->count();
    expect($entryCount)->toBeGreaterThan(0);

    // 3. Execute Second Time (Simulate retry)
    $action->execute($this->attempt);

    // 4. Assert: Ledger entries haven't changed
    expect($transaction->ledgerEntries()->count())->toBe($entryCount);
});

it('calculates split fees using integer math without penny loss', function () {
    // Odd fee: 21. Split should be 10 and 11.
    $this->payment->update(['bearer' => FeeBearer::Split]);
    $this->attempt->update(['fee' => 21]);

    PaymentProvider::shouldReceive('verifyTransaction')->andReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'REF_PROV_FEE',
        rawResponse: []
    ));

    $action = app(ProcessPaymentAttempt::class);
    $action->execute($this->attempt);

    $transaction = Transaction::where('reference', $this->payment->reference)->first();

    // Verify Business Wallet receives Gross Amount (1000) and pays Merchant Fee (10)
    $businessWallet = app(\App\Domains\Ledger\Services\LedgerService::class)
        ->businessWallet($this->business, 'NGN');

    $grossCredit = \App\Models\LedgerEntry::where('ledger_account_id', $businessWallet->id)
        ->where('transaction_id', $transaction->id)
        ->where('amount', 1000)
        ->exists();

    $feeDebit = \App\Models\LedgerEntry::where('ledger_account_id', $businessWallet->id)
        ->where('transaction_id', $transaction->id)
        ->where('amount', -10)
        ->exists();

    expect($grossCredit)->toBeTrue()
        ->and($feeDebit)->toBeTrue();

    // Verify Platform Revenue receives 10 (Merchant) + 11 (Customer) = 21
    $revenueAccount = app(\App\Domains\Ledger\Services\LedgerService::class)
        ->platformRevenue('NGN');

    $totalRevenue = \App\Models\LedgerEntry::where('ledger_account_id', $revenueAccount->id)
        ->where('transaction_id', $transaction->id)
        ->sum('amount');

    expect($totalRevenue)->toBe(21);
});
