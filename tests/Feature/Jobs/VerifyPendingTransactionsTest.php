<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Jobs\VerifyPendingTransactions;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
    ]);

    $this->provider = Provider::create([
        'name' => 'Test Provider',
        'identifier' => 'test_provider',
        'is_active' => true,
        'is_healthy' => true,
        'supported_channels' => [PaymentChannel::Card->value],
        'metadata' => ['fee_percentage' => 0.1],
    ]);
});

it('processes old pending attempts', function () {
    // 1. Setup Data
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Pending,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'OLD_REF',
        'status' => AuthorizationStatus::Pending,
        'amount' => 1000,
        'currency' => 'NGN',
        'fee' => 10,
        'idempotency_key' => 'key_1',
        'updated_at' => now()->subMinutes(6), // Older than 5 mins
        'completed_at' => null,
    ]);

    // 2. Mock Action
    $processor = Mockery::mock(ProcessPaymentAttempt::class);
    $processor->shouldReceive('execute')->once()->with(Mockery::on(fn ($arg) => $arg->id === $attempt->id));

    // 3. Execute Job
    $job = new VerifyPendingTransactions();
    $job->handle($processor);
});

it('ignores recent attempts', function () {
    // 1. Setup Data
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Pending,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'RECENT_REF',
        'status' => AuthorizationStatus::Pending,
        'amount' => 1000,
        'currency' => 'NGN',
        'fee' => 10,
        'idempotency_key' => 'key_2',
        'updated_at' => now()->subMinutes(1), // Too recent
        'completed_at' => null,
    ]);

    // 2. Mock Action
    $processor = Mockery::mock(ProcessPaymentAttempt::class);
    $processor->shouldNotReceive('execute');

    // 3. Execute Job
    $job = new VerifyPendingTransactions();
    $job->handle($processor);
});

it('ignores completed attempts', function () {
    // 1. Setup Data
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Pending,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'COMPLETED_REF',
        'status' => AuthorizationStatus::Success,
        'amount' => 1000,
        'currency' => 'NGN',
        'fee' => 10,
        'idempotency_key' => 'key_3',
        'updated_at' => now()->subMinutes(10),
        'completed_at' => now(), // Already completed
    ]);

    // 2. Mock Action
    $processor = Mockery::mock(ProcessPaymentAttempt::class);
    $processor->shouldNotReceive('execute');

    // 3. Execute Job
    $job = new VerifyPendingTransactions();
    $job->handle($processor);
});
