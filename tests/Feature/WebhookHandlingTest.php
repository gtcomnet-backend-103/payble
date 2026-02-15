<?php

declare(strict_types=1);

use App\Domains\Payments\Actions\AuthorizePayment;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\FeeConfig;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    // Create Paystack provider since we are using real adapter
    $this->provider = Provider::create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'is_active' => true,
        'is_healthy' => true,
        'supported_channels' => ['card'],
    ]);

    config(['services.paystack.secret' => 'test_secret']);
});

function getPaystackSignature(array $payload): string
{
    return hash_hmac('sha512', json_encode($payload), 'test_secret');
}

it('receives and processes a successful provider webhook', function () {
    // 1. Setup pending authorization
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_WEBHOOK_1',
        'bearer' => App\Enums\FeeBearer::ACCOUNT,
    ]);

    FeeConfig::factory()->create([
        'fixed_amount' => 20,
        'channel' => 'card',
    ]);

    // Mock Paystack fee - PaystackAdapter::getFee returns 1000 (10.00) fix
    // but we can mock the config if needed or just use what adapter returns.
    // PaystackAdapter::getFee is currently hardcoded to 1000.

    $attempt = app(AuthorizePayment::class)->createAttempt($payment, PaymentChannel::Card);
    $attempt->update([
        'status' => AuthorizationStatus::Pending,
        'provider_reference' => 'REF_123',
    ]);

    // 2. Mock Webhook Interaction
    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 12345,
            'reference' => 'REF_123',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // Added: Mock verifyTransaction call which is part of ProcessPaymentAttempt
    Http::fake([
        'api.paystack.co/transaction/verify/REF_123' => Http::response([
            'status' => true,
            'data' => [
                'id' => 12345,
                'status' => 'success',
                'reference' => 'REF_123',
                'amount' => 1000,
                'currency' => 'NGN',
            ],
        ]),
    ]);

    // 3. Send Webhook
    postJson('/api/webhooks/paystack', $payload, [
        'x-paystack-signature' => getPaystackSignature($payload),
    ])->assertStatus(200);

    // 4. Verify Persistence
    assertDatabaseHas('webhook_events', [
        'provider' => 'paystack',
        'event_type' => 'charge.success',
    ]);

    $event = WebhookEvent::first();

    // 5. Run the Job
    app(App\Jobs\ProcessWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Payments\Actions\ProcessPaymentAttempt::class)
    );

    // 6. Assert State Changes
    $attempt->refresh();
    expect($attempt->status)->toBe(AuthorizationStatus::Success);

    assertDatabaseHas('transactions', [
        'reference' => $payment->reference,
        'status' => 'success',
    ]);

    // 7. Assert Ledger Postings
    $ledger = app(App\Domains\Ledger\Services\LedgerService::class);
    $clearing = $ledger->providerReceivable($this->provider, 'NGN', App\Enums\PaymentMode::Test);
    $customerFunds = $ledger->customerWallet($payment->customer, 'NGN', App\Enums\PaymentMode::Test);
    $platformRevenue = $ledger->platformRevenue('NGN', App\Enums\PaymentMode::Test);
    $providerFee = $ledger->providerFee($this->provider, 'NGN', App\Enums\PaymentMode::Test);
    $businessWallet = $ledger->businessReceivable($this->business, 'NGN', App\Enums\PaymentMode::Test);

    expect($clearing)->not->toBeNull()
        ->and($customerFunds)->not->toBeNull()
        ->and($platformRevenue)->not->toBeNull()
        ->and($businessWallet)->not->toBeNull()
        ->and($ledger->getBalance($customerFunds))->toBe(0)
        ->and($ledger->getBalance($clearing))->toBe(20) // 1020 (Dr) - 1000 (Cr) = 20
        ->and($ledger->getBalance($providerFee))->toBe(1000) // Paystack fee
        ->and($ledger->getBalance($platformRevenue))->toBe(-20) // 20 Customer Fee (Cr)
        ->and($ledger->getBalance($businessWallet))->toBe(-1000); // 1000 Merchant Net (Cr)
    // Wait: Gross is 1000 + 20 fee = 1020.
    // Provider gets 1020. Fee is 1000. Net in provider is 20.
    // Business gets 1000? Let's re-run and see actuals if it fails.
    // PaystackAdapter::getFee returns 1000.
    // RecordPaymentLedgerPostings logic:
    // $gross = 1020 (if bearer is customer, and fixed_amount is 20)
    // $providerFee = 1000
    // $businessFee = 0 (no merchant fee config)
    // $customerFee = 20
    // $netInProviderAccount = 1020 - 1000 = 20
    // $totalPlatformRevenue = 0 + 20 = 20
    // $merchantNet = 1020 - 20 = 1000

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
});

it('prevents double processing of the same webhook event', function () {
    WebhookEvent::create([
        'provider' => 'paystack',
        'provider_event_id' => '12345',
        'raw_payload' => ['foo' => 'bar'],
        'processed_at' => now(),
    ]);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 12345,
            'reference' => 'REF_1',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // Controller should handle duplicate check via CreateWebhookEvent action
    postJson('/api/webhooks/paystack', $payload, [
        'x-paystack-signature' => getPaystackSignature($payload),
    ])->assertStatus(200);

    expect(WebhookEvent::count())->toBe(1);
});

it('rejects webhooks with invalid signature', function () {
    postJson('/api/webhooks/paystack', [], [
        'x-paystack-signature' => 'invalid_signature',
    ])->assertStatus(401)
        ->assertJson(['message' => 'Invalid signature']);
});

it('handles webhook for non-existent payment reference', function () {
    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 'EVT_UNKNOWN',
            'reference' => 'UNKNOWN_REF',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // 1. Send Webhook
    postJson('/api/webhooks/paystack', $payload, [
        'x-paystack-signature' => getPaystackSignature($payload),
    ])->assertStatus(200);

    $event = WebhookEvent::where('provider_event_id', 'EVT_UNKNOWN')->first();

    // 2. Run the job
    app(App\Jobs\ProcessWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Payments\Actions\ProcessPaymentAttempt::class)
    );

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
    expect($event->feedback)->toContain('No payment attempt');
});

it('ignores already processed payments', function () {
    // Setup a successful payment
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_SUCCESS',
        'status' => App\Enums\PaymentStatus::Success,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'PAYSTACK_REF_SUCCESS',
        'status' => AuthorizationStatus::Success,
        'currency' => 'NGN',
        'amount' => 1000,
        'fee' => 20,
        'idempotency_key' => 'IDEM_KEY_ALREADY_PROCESSED',
    ]);

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'id' => 'EVT_SUCCESS_AGAIN',
            'reference' => 'PAYSTACK_REF_SUCCESS',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // 1. Send Webhook
    postJson('/api/webhooks/paystack', $payload, [
        'x-paystack-signature' => getPaystackSignature($payload),
    ])->assertStatus(200);

    $event = WebhookEvent::where('provider_event_id', 'EVT_SUCCESS_AGAIN')->first();

    // 2. Run the job
    app(App\Jobs\ProcessWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Payments\Actions\ProcessPaymentAttempt::class)
    );

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
    expect($event->feedback)->toContain('payment already processed');
});

it('does not re-process an already processed webhook event', function () {
    $event = WebhookEvent::create([
        'provider' => 'paystack',
        'provider_event_id' => 'EVT_PROCESSED',
        'raw_payload' => [],
        'event_type' => 'charge.success',
        'processed_at' => now()->subHour(),
        'feedback' => 'original processing',
    ]);

    // Run the job
    app(App\Jobs\ProcessWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Payments\Actions\ProcessPaymentAttempt::class)
    );

    $event->refresh();
    // Feedback should not change if it returned early
    expect($event->feedback)->toBe('original processing');
});
