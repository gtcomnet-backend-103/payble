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
        'amount' => 100000,
        'reference' => 'REF_WEBHOOK_1',
        'bearer' => App\Enums\FeeBearer::ACCOUNT,
    ]);

    FeeConfig::factory()->create([
        'fixed_amount' => 2000,
        'channel' => 'card',
    ]);

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
    $platformRevenue = $ledger->platformRevenue('NGN', App\Enums\PaymentMode::Test);
    $providerFee = $ledger->providerFee($this->provider, 'NGN', App\Enums\PaymentMode::Test);
    $businessWallet = $ledger->businessReceivable($this->business, 'NGN', App\Enums\PaymentMode::Test);

    expect($clearing)->not->toBeNull()
        ->and($platformRevenue)->not->toBeNull()
        ->and($businessWallet)->not->toBeNull()
        ->and($ledger->getBalance($clearing))->toBe(99000)
        ->and($ledger->getBalance($providerFee))->toBe(1000)
        ->and($ledger->getBalance($platformRevenue))->toBe(-2000)
        ->and($ledger->getBalance($businessWallet))->toBe(-98000);

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
        'intent_id' => $payment->id,
        'intent_type' => $payment->getMorphClass(),
        'provider_id' => $this->provider->id,
        'channel' => App\Enums\FeeChannel::CARD,
        'provider_reference' => 'PAYSTACK_REF_SUCCESS',
        'status' => AuthorizationStatus::Success,
        'currency' => 'NGN',
        'amount' => 1000,
        'fee' => 20,
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
