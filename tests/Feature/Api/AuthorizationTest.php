<?php

declare(strict_types=1);

use App\Domains\Payments\Providers\DataTransferObjects\BankDetailsDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\FeeConfig;
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
        'supported_channels' => [PaymentChannel::Card->value, PaymentChannel::BankTransfer->value],
        'metadata' => ['fee_percentage' => 0.1],
    ]);

    FeeConfig::create([
        'channel' => PaymentChannel::Card,
        'percentage' => 1.5,
        'fixed_amount' => 100,
        'is_active' => true,
    ]);

    FeeConfig::create([
        'channel' => PaymentChannel::BankTransfer,
        'percentage' => 0,
        'fixed_amount' => 50,
        'is_active' => true,
    ]);

    PaymentProvider::fake();
});

it('authorizes a card payment with empty authorization array', function () {

    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_123',
        'amount' => 10000,
    ]);

    PaymentProvider::fake()->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'TEST_PROV_REF',
        rawResponse: ['status' => 'success']
    ));

    $response = $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', AuthorizationStatus::Success->value)
        ->assertJsonPath('amount', 10000)
        ->assertJsonPath('reference', 'REF_123')
        ->assertJsonPath('authorization', [])
        ->assertJsonStructure([
            'status',
            'amount',
            'reference',
            'customer' => ['first_name', 'last_name', 'email', 'phone'],
            'fee',
            'authorization',
        ]);
});

it('authorizes a bank transfer payment with details in authorization array', function () {

    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_456',
        'amount' => 10000,
    ]);

    PaymentProvider::fake()->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::PendingTransfer,
        providerReference: 'TEST_BANK_REF',
        bankDetails: new BankDetailsDTO(
            accountNumber: '1234567890',
            bankName: 'Test Bank',
            accountName: 'Test Account'
        ),
        rawResponse: ['provider' => 'fake']
    ));

    $response = $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'bank_transfer',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', AuthorizationStatus::PendingTransfer->value)
        ->assertJsonPath('reference', 'REF_456')
        ->assertJsonPath('amount', 10000)
        ->assertJsonStructure([
            'status',
            'amount',
            'reference',
            'customer',
            'fee',
            'authorization' => ['account_number', 'bank_name'],
        ]);

    $this->assertDatabaseHas('authorization_attempts', [
        'payment_intent_id' => $payment->id,
        'channel' => 'bank_transfer',
        'status' => AuthorizationStatus::PendingTransfer->value,
    ]);
});

it('returns dynamic action if required', function () {

    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_ACTION',
        'amount' => 10000,
    ]);

    PaymentProvider::fake()->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::PendingPin,
        providerReference: 'TEST_ACTION_REF',
        rawResponse: ['foo' => 'bar']
    ));

    $response = $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('reference', 'REF_ACTION')
        ->assertJsonPath('amount', 10000)
        ->assertJsonPath('action', 'pin');
});

it('is idempotent per channel', function () {

    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Initiated,
        'reference' => 'REF_789',
    ]);

    $fake = PaymentProvider::fake();
    $fake->shouldReturn(new ProviderResponse(
        status: AuthorizationStatus::Success,
        providerReference: 'IDEM_REF'
    ));

    // First call
    $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $countBefore = AuthorizationAttempt::count();

    // Second call
    $response = $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $response->assertStatus(200);
    $this->assertEquals($countBefore, AuthorizationAttempt::count());
});

it('rejects authorization if already successful', function () {

    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'status' => PaymentStatus::Success,
        'reference' => 'REF_999',
    ]);

    $response = $this->postJson("/api/payments/{$payment->reference}/authorize", [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $response->assertStatus(400)
        ->assertJsonPath('message', 'Payment has already been successful.');
});
