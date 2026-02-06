<?php

declare(strict_types=1);

use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
    ]);
    $this->user->businesses()->attach($this->business);
    Sanctum::actingAs($this->business, ['*'], 'business');

    $this->provider = Provider::create([
        'name' => 'Test Provider',
        'identifier' => 'test_provider',
        'is_active' => true,
        'is_healthy' => true,
        'supported_channels' => [PaymentChannel::Card->value],
    ]);
});

it('validates a pending pin payment and requests otp', function () {
    // 1. Setup Payment and Initial Attempt
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_PIN',
        'amount' => 5000,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'status' => AuthorizationStatus::PendingPin,
        'provider_reference' => 'PROV_REF_1',
        'amount' => 5000,
        'currency' => $payment->currency,
        'fee' => 0,
        'provider_fee' => 0,
        'idempotency_key' => 'SETUP_KEY_1',
    ]);

    // 2. Mock Provider
    PaymentProvider::fake()->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::PendingOtp,
        providerReference: 'PROV_REF_1',
        rawResponse: ['message' => 'OTP required']
    ));

    // 3. Make Request
    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'pin' => '1234',
    ]);

    // 4. Assertions
    $response->assertStatus(200)
        ->assertJsonPath('action', 'otp');

    // Should create a NEW attempt
    $this->assertDatabaseCount('authorization_attempts', 2);
    $this->assertDatabaseHas('authorization_attempts', [
        'payment_intent_id' => $payment->id,
        'status' => AuthorizationStatus::PendingOtp->value,
        'provider_reference' => 'PROV_REF_1',
    ]);
});

it('validates otp and finalizes payment', function () {
    // 1. Setup Payment and Previous Attempt
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_OTP',
        'amount' => 5000,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'status' => AuthorizationStatus::PendingOtp,
        'provider_reference' => 'PROV_REF_2',
        'amount' => 5000,
        'currency' => $payment->currency,
        'fee' => 100,
        'provider_fee' => 50,
        'idempotency_key' => 'SETUP_KEY_2',
    ]);

    // 2. Mock Provider Success
    PaymentProvider::fake()->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'PROV_REF_2',
        rawResponse: ['status' => 'success']
    ));

    // 3. Make Request
    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'otp' => '123456',
    ]);

    // 4. Assertions
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    // Payment should be successful
    $this->assertDatabaseHas('payment_intents', [
        'id' => $payment->id,
        'status' => PaymentStatus::Success->value,
    ]);

    // Transaction should be created (ProcessPaymentAttempt called)
    $this->assertDatabaseHas('transactions', [
        'payment_intent_id' => $payment->id,
        'status' => PaymentStatus::Success->value,
    ]);
});

it('rejects validation for already successful payment', function () {
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Success,
        'reference' => 'REF_DONE',
    ]);

    $response = $this->postJson("/api/payments/{$payment->reference}/validate", ['otp' => '1234']);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Payment has already been successful.');
});

it('validates request data', function () {
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'reference' => 'REF_VAL',
    ]);

    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'pin' => '12', // Too short
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);
});
