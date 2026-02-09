<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Payments\Actions;

use App\Domains\Payments\Actions\AuthorizePayment;
use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Domains\Payments\Events\TransactionSuccessful;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    // Sync actual providers
    Artisan::call('payment:providers-sync');
    $this->provider = Provider::where('identifier', 'paystack')->first();

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
    Event::fake();

    // 1. Mock HTTP response from Paystack
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'REF_PROV_123',
                'amount' => 1000,
            ],
        ]),
    ]);

    // 2. Execute Action
    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeTrue();

    // 3. Verify State Changes
    $this->attempt->refresh();
    $this->payment->refresh();

    expect($this->attempt->status)->toBe(AuthorizationStatus::Success);
    expect($this->payment->status)->toBe(PaymentStatus::Success);

    $transaction = Transaction::where('reference', $this->payment->reference)->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->status)->toBe(TransactionStatus::Success);
    expect($transaction->amount)->toBe(1000);

    // 4. Assert Event Dispatched
    Event::assertDispatched(TransactionSuccessful::class, function ($event) use ($transaction) {
        return $event->transaction->id === $transaction->id
            && $event->provider->id === $this->provider->id
            && $event->totalFee === 0; // Assuming 0 fee for this test case
    });
});

it('returns false when provider verification fails', function () {
    Event::fake();

    // 1. Mock HTTP response as pending
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'pending',
                'reference' => 'REF_PROV_123',
            ],
        ]),
    ]);

    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeFalse();

    $this->attempt->refresh();
    expect($this->attempt->status)->toBe(AuthorizationStatus::Pending);

    Event::assertNotDispatched(TransactionSuccessful::class);
});

it('transitions to failed when provider HTTP response fails', function () {
    Event::fake();

    // 1. Mock HTTP to fail
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response(null, 500),
    ]);

    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    // HTTP failure returns Failed status, which is a final status that gets processed
    expect($result)->toBeTrue();

    // Status should be Failed
    $this->attempt->refresh();
    expect($this->attempt->status)->toBe(AuthorizationStatus::Failed);

    Event::assertNotDispatched(TransactionSuccessful::class);
});

it('ensures transaction exists (idempotency)', function () {
    Event::fake();

    // Transaction is automatically created by factory hook.
    // Use it to simulate an existing pending transaction.
    $existingTx = $this->payment->transaction;
    $existingTx->update([
        'status' => 'pending',
        'mode' => 'live',
    ]);

    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'REF_PROV_123',
                'amount' => 1000,
            ],
        ]),
    ]);

    $action = app(ProcessPaymentAttempt::class);
    $result = $action->execute($this->attempt);

    expect($result)->toBeTrue();

    $existingTx->refresh();
    expect($existingTx->status)->toBe(TransactionStatus::Success);
    expect(Transaction::count())->toBe(1);

    Event::assertDispatched(TransactionSuccessful::class);
});

it('ensures no double-dispatch of event (idempotent)', function () {
    Event::fake();

    // 1. Mock HTTP
    Http::fake([
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'REF_PROV_IDEM',
                'amount' => 1000,
            ],
        ]),
    ]);

    // 2. Execute First Time
    $action = app(ProcessPaymentAttempt::class);
    $action->execute($this->attempt);

    Event::assertDispatched(TransactionSuccessful::class, 1);

    // 3. Execute Second Time (Simulate retry)
    $action->execute($this->attempt);

    // 4. Assert: Event dispatched only once
    Event::assertDispatched(TransactionSuccessful::class, 1);
});
