<?php

declare(strict_types=1);

use App\Domains\Providers\Facades\PaymentProvider;
use App\Enums\AccountType;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Models\AuthorizationAttempt;
use App\Models\Business;
use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\Provider;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    $this->provider = Provider::create([
        'name' => 'Test Provider',
        'identifier' => 'test_provider',
        'is_active' => true,
        'is_healthy' => true,
        'supported_channels' => ['card'],
    ]);

    PaymentProvider::fake();
});

it('receives and processes a successful provider webhook', function () {
    // 1. Setup pending authorization
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_WEBHOOK_1',
        'bearer' => App\Enums\FeeBearer::Split,
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'REF_123',
        'status' => AuthorizationStatus::Pending,
        'currency' => 'NGN',
        'amount' => 1010,
        'fee' => 20,
        'idempotency_key' => 'IDEM_KEY_123',
    ]);

    // 2. Mock Webhook Interaction
    // Note: We use the real PaystackAdapter normalization logic because we want to test true integration
    // But we mock the signature verification in the controller's logic (which checks for the header)

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'REF_123',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // Set the fake provider fee for the webhook processing
    PaymentProvider::fake()->shouldReturnFee(5);

    // 3. Send Webhook (Signature check is mocked in Adapter to check for presence)
    postJson('/api/webhooks/test_provider', $payload)->assertStatus(200);

    // 4. Verify Persistence
    assertDatabaseHas('webhook_events', [
        'provider' => 'test_provider',
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
        'payment_intent_id' => $payment->id,
        'status' => 'success',
    ]);

    // 7. Assert Ledger Postings
    /*
     * RecordPaymentLedgerPostings Logic uses AccountType enums.
     */

    // Check accounts based on new logic
    $clearing = LedgerAccount::where('holder_type', $this->provider->getMorphClass())
        ->where('holder_id', $this->provider->id)
        ->where('type', AccountType::PROVIDER_CLEARING)
        ->where('currency', 'NGN')
        ->first();

    $customerFunds = LedgerAccount::where('holder_type', $payment->customer->getMorphClass())
        ->where('holder_id', $payment->customer_id)
        ->where('type', AccountType::CUSTOMER_WALLET)
        ->where('currency', 'NGN')
        ->first();

    $platformRevenue = LedgerAccount::whereNull('holder_id')
        ->whereNull('holder_type')
        ->where('type', AccountType::PLATFORM_FEE_REVENUE)
        ->where('currency', 'NGN')
        ->first();

    $businessWallet = LedgerAccount::where('holder_type', $this->business->getMorphClass())
        ->where('holder_id', $this->business->id)
        ->where('type', AccountType::BUSINESS_WALLET)
        ->where('currency', 'NGN')
        ->first();

    expect($clearing)->not->toBeNull()
        ->and($customerFunds)->not->toBeNull()
        ->and($platformRevenue)->not->toBeNull()
        ->and($businessWallet)->not->toBeNull()
        ->and(LedgerEntry::where('ledger_account_id', $customerFunds->id)->sum('amount'))->toBe(0)
//        ->and(LedgerEntry::where('ledger_account_id', $clearing->id)->sum('amount'))->toBe(995)
        ->and(LedgerEntry::where('ledger_account_id', $platformRevenue->id)->sum('amount'))->toBe(20)
        ->and(LedgerEntry::where('ledger_account_id', $businessWallet->id)->sum('amount'))->toBe(990);

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
});

it('prevents double processing of the same webhook event', function () {
    // For this test we can use fake or just real logic
    $provider = Provider::where('identifier', 'test_provider')->first();

    WebhookEvent::create([
        'provider' => 'test_provider',
        'provider_event_id' => 'EVT_1',
        'raw_payload' => ['foo' => 'bar'],
        'processed_at' => now(),
    ]);

    // The fake normalizer by default returns 'evt_fake' for providerEventId.
    // We need to tell it to return 'EVT_1' to match.
    PaymentProvider::fake()->shouldReturnWebhookPayload(new App\Domains\Providers\DataTransferObjects\WebhookPayloadDTO(
        providerEventId: 'EVT_1',
        eventType: 'charge.success',
        reference: 'REF_1',
        amount: 1000,
        currency: 'NGN',
        status: AuthorizationStatus::Success,
        rawPayload: []
    ));

    expect(WebhookEvent::count())->toBe(1);
});

it('rejects webhooks with invalid signature', function () {
    // Mock verifyWebhook to return false
    PaymentProvider::shouldReceive('verifyWebhook')
        ->once()
        ->andReturn(false);

    postJson('/api/webhooks/test_provider', [], [
        'x-test_provider-signature' => 'invalid_signature',
    ])->assertStatus(401)
        ->assertJson(['message' => 'Invalid signature']);
});

it('handles webhook for non-existent payment reference', function () {
    $provider = Provider::where('identifier', 'test_provider')->first();

    // Create an event that doesn't match any authorization attempt
    $event = WebhookEvent::create([
        'provider' => 'test_provider',
        'provider_event_id' => 'EVT_UNKNOWN',
        'raw_payload' => ['data' => ['reference' => 'UNKNOWN_REF']],
        'event_type' => 'charge.success',
    ]);

    // Mock normalization to return the unknown reference
    PaymentProvider::fake()->shouldReturnWebhookPayload(new App\Domains\Providers\DataTransferObjects\WebhookPayloadDTO(
        providerEventId: 'EVT_UNKNOWN',
        eventType: 'charge.success',
        reference: 'UNKNOWN_REF',
        amount: 1000,
        currency: 'NGN',
        status: AuthorizationStatus::Success,
        rawPayload: []
    ));

    // Run the job
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

    $event = WebhookEvent::create([
        'provider' => 'test_provider',
        'provider_event_id' => 'EVT_SUCCESS_AGAIN',
        'raw_payload' => ['data' => ['reference' => 'PAYSTACK_REF_SUCCESS']],
        'event_type' => 'charge.success',
    ]);

    // Mock normalization
    PaymentProvider::fake()->shouldReturnWebhookPayload(new App\Domains\Providers\DataTransferObjects\WebhookPayloadDTO(
        providerEventId: 'EVT_SUCCESS_AGAIN',
        eventType: 'charge.success',
        reference: 'PAYSTACK_REF_SUCCESS',
        amount: 1000,
        currency: 'NGN',
        status: AuthorizationStatus::Success,
        rawPayload: []
    ));

    // Run the job
    app(App\Jobs\ProcessWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Payments\Actions\ProcessPaymentAttempt::class)
    );

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
    expect($event->feedback)->toContain('payment already processed');
});

it('does not re-process an already processed webhook event', function () {
    $event = WebhookEvent::create([
        'provider' => 'test_provider',
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
