<?php

declare(strict_types=1);

use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentStatus;
use App\Models\Business;
use App\Models\FeeConfig;
use App\Models\PaymentIntent;
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
    Artisan::call('payment:providers-sync');

    FeeConfig::create([
        'channel' => PaymentChannel::Card,
        'percentage' => 0,
        'fixed_amount' => 1000,
        'is_active' => true,
    ]);

    FeeConfig::create([
        'channel' => PaymentChannel::BankTransfer,
        'percentage' => 0,
        'fixed_amount' => 500,
        'is_active' => true,
    ]);
});

it('can authorizes a card payment', function () {
    Illuminate\Support\Facades\Http::fake([
        'api.paystack.co/charge' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'success',
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'success',
            ],
        ]),
    ]);
    $this->postJson('/api/payments', [
        'amount' => 10000,
        'email' => 'test@email.com',
        'reference' => 'ref-1234567890',
    ]);

    $response = $this->postJson('/api/payments/ref-1234567890/authorize', [
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
        ->assertJsonPath('reference', 'ref-1234567890')
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

it('can authorizes a bank transfer payment', function () {
    Illuminate\Support\Facades\Http::fake([
        'api.paystack.co/charge' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'success',
                'bank' => [
                    'account_number' => '1234567890',
                    'account_name' => 'Test User',
                    'name' => 'Test Bank',
                ],
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'success',
            ],
        ]),
    ]);

    $this->postJson('/api/payments', [
        'amount' => 10000,
        'email' => 'test@email.com',
        'reference' => 'ref-1234567890',
    ]);

    $response = $this->postJson('/api/payments/ref-1234567890/authorize', [
        'channel' => 'bank_transfer',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('status', AuthorizationStatus::Success->value)
        ->assertJsonPath('amount', 10000)
        ->assertJsonPath('reference', 'ref-1234567890')
        ->assertJsonPath('authorization', [
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'account_name' => 'Test User',
            'expires_at' => null,
        ])
        ->assertJsonStructure([
            'status',
            'amount',
            'reference',
            'customer' => ['first_name', 'last_name', 'email', 'phone'],
            'fee',
        ]);
});

it('returns dynamic action if required', function () {
    Illuminate\Support\Facades\Http::fake([
        'api.paystack.co/charge' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'send_pin',
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'send_pin',
            ],
        ]),
    ]);

    $this->postJson('/api/payments', [
        'amount' => 10000,
        'email' => 'test@email.com',
        'reference' => 'ref-1234567890',
    ]);

    $response = $this->postJson('/api/payments/ref-1234567890/authorize', [
        'channel' => 'card',
        'card' => [
            'number' => '1234567890123456',
            'cvv' => '123',
            'expiry_month' => '12',
            'expiry_year' => '30',
        ],
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('reference', 'ref-1234567890')
        ->assertJsonPath('amount', 10000)
        ->assertJsonPath('action', 'pin');
});

it('is idempotent per channel', function () {
    Illuminate\Support\Facades\Http::fake([
        'api.paystack.co/charge' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'send_pin',
            ],
        ]),
        'api.paystack.co/transaction/verify/*' => Illuminate\Support\Facades\Http::response([
            'data' => [
                'amount' => 10000,
                'status' => 'send_pin',
            ],
        ]),
    ]);

    $this->postJson('/api/payments', [
        'amount' => 10000,
        'email' => 'test@email.com',
        'reference' => 'ref-1234567890',
    ]);

    $this->postJson('/api/payments/ref-1234567890/authorize', [
        'channel' => 'bank_transfer',
    ]);

    Illuminate\Support\Facades\Http::assertSentCount(1);

    $this->postJson('/api/payments/ref-1234567890/authorize', [
        'channel' => 'bank_transfer',
    ])->assertBadRequest();

    Illuminate\Support\Facades\Http::assertSentCount(1);
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
        ->assertJsonPath('message', 'Payment has already been authorized.');
});
