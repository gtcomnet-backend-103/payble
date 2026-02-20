<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\AuthorizationStatus;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::factory()->create([
        'owner_id' => $this->user->id,
    ]);
    $this->user->businesses()->attach($this->business);
    Sanctum::actingAs($this->business, ['*'], 'business');

    // Ensure providers are synced and use Paystack for these tests
    Artisan::call('payment:providers-sync');
    $this->provider = Provider::where('identifier', 'paystack')->first();
});

it('validates a pending pin payment and transitions to pending otp', function () {
    // 1. Setup Payment and Initial Attempt
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
    ]);

    AuthorizationAttempt::create([
        'intent_id' => $payment->id,
        'intent_type' => $payment->getMorphClass(),
        'provider_id' => $this->provider->id,
        'channel' => \App\Enums\FeeChannel::CARD,
        'status' => AuthorizationStatus::PendingPin,
        'provider_reference' => 'PROV_REF_PIN',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'fee' => 0,
        'provider_fee' => 0,
    ]);

    // 2. Mock Paystack submit_pin response
    Http::fake([
        'api.paystack.co/charge/submit_pin' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'send_otp',
                'reference' => 'PROV_REF_PIN',
                'message' => 'Please enter OTP',
            ],
        ]),
    ]);

    // 3. Make Request
    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'pin' => '1234',
    ]);

    // 4. Assertions
    $response->assertStatus(200)
        ->assertJsonPath('action', 'otp') // Inferred from PendingOtp status
        ->assertJsonPath('reference', $payment->reference);

    // Should create a NEW attempt for the validation step
    $this->assertDatabaseCount('authorization_attempts', 2);
    $this->assertDatabaseHas('authorization_attempts', [
        'intent_id' => $payment->id,
        'intent_type' => $payment->getMorphClass(),
        'status' => AuthorizationStatus::PendingOtp->value,
        'provider_reference' => 'PROV_REF_PIN',
    ]);
});

it('validates a pending phone payment and transitions to success', function () {
    // 1. Setup Payment and Initial Attempt
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
    ]);

    AuthorizationAttempt::create([
        'intent_id' => $payment->id,
        'intent_type' => $payment->getMorphClass(),
        'provider_id' => $this->provider->id,
        'channel' => \App\Enums\FeeChannel::CARD,
        'status' => AuthorizationStatus::PendingPhone,
        'provider_reference' => 'PROV_REF_PHONE',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'fee' => 0,
        'provider_fee' => 0,
    ]);

    // 2. Mock Paystack submit_phone response
    Http::fake([
        'api.paystack.co/charge/submit_phone' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PROV_REF_PHONE',
                'amount' => $payment->amount,
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PROV_REF_PHONE',
                'amount' => $payment->amount,
                'customer' => ['email' => 'test@email.com'],
            ],
        ]),
    ]);

    // 3. Make Request
    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'phone' => '08012345678',
    ]);

    // 4. Assertions
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success');

    $this->assertDatabaseHas('payment_intents', [
        'id' => $payment->id,
        'status' => PaymentStatus::Success,
    ]);
});

it('validates otp and successfuly finalizes payment', function () {
    // 1. Setup Payment and Previous Attempt
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
    ]);

    AuthorizationAttempt::create([
        'intent_id' => $payment->id,
        'intent_type' => $payment->getMorphClass(),
        'provider_id' => $this->provider->id,
        'channel' => \App\Enums\FeeChannel::CARD,
        'status' => AuthorizationStatus::PendingOtp,
        'provider_reference' => 'PROV_REF_OTP',
        'amount' => $payment->amount,
        'currency' => $payment->currency,
        'fee' => 100,
        'provider_fee' => 50,
    ]);

    // 2. Mock Paystack submit_otp success response
    Http::fake([
        'api.paystack.co/charge/submit_otp' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PROV_REF_OTP',
                'amount' => $payment->amount,
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Http::response([
            'status' => true,
            'data' => [
                'status' => 'success',
                'reference' => 'PROV_REF_OTP',
                'amount' => $payment->amount,
                'customer' => ['email' => 'test@email.com'],
            ],
        ]),
    ]);

    // 3. Make Request
    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'otp' => '123456',
    ]);

    // 4. Assertions
    $response->assertStatus(200)
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('reference', $payment->reference);

    // Payment should be successful
    $this->assertDatabaseHas('payment_intents', [
        'id' => $payment->id,
        'status' => PaymentStatus::Success->value,
    ]);

    // Transaction should be created
    $this->assertDatabaseHas('transactions', [
        'reference' => $payment->reference,
        'status' => TransactionStatus::Success->value,
    ]);
});

it('rejects validation for already successful payment', function () {
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Success,
    ]);

    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'otp' => '123456',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Payment has already been authorized.');
});

it('fails validation when no pending validating attempt exists', function () {
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
    ]);

    // No attempt in DB

    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'pin' => '1234',
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'No pending authorization attempt found.');
});

it('validates request data rules', function () {
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
    ]);

    $response = $this->postJson("/api/payments/{$payment->reference}/validate", [
        'pin' => '12', // Too short (min:4)
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['pin']);
});
