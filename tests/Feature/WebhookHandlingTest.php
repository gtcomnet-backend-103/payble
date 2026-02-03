<?php

declare(strict_types=1);

use App\Domains\Providers\Facades\PaymentProvider;
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

    // Initialize business ledger accounts
    app(App\Domains\Ledger\Actions\CreateLedgerAccounts::class)->execute($this->business);

    $this->provider = Provider::create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'is_active' => true,
        'is_healthy' => true,
        'supported_channels' => ['card'],
    ]);

    PaymentProvider::fake();
});

it('receives and processes a successful paystack webhook', function () {
    // 1. Setup pending authorization
    $payment = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'reference' => 'REF_WEBHOOK_1',
    ]);

    $attempt = AuthorizationAttempt::create([
        'payment_intent_id' => $payment->id,
        'provider_id' => $this->provider->id,
        'channel' => PaymentChannel::Card,
        'provider_reference' => 'PAYSTACK_REF_123',
        'status' => AuthorizationStatus::PendingPin,
        'currency' => 'NGN',
        'fee_amount' => 0,
        'idempotency_key' => 'IDEM_KEY_123',
    ]);

    // 2. Mock Webhook Interaction
    // Note: We use the real PaystackAdapter normalization logic because we want to test true integration
    // But we mock the signature verification in the controller's logic (which checks for the header)

    $payload = [
        'event' => 'charge.success',
        'data' => [
            'reference' => 'PAYSTACK_REF_123',
            'amount' => 1000,
            'currency' => 'NGN',
            'status' => 'success',
        ],
    ];

    // 3. Send Webhook (Signature check is mocked in Adapter to check for presence)
    postJson('/api/webhooks/paystack', $payload, [
        'x-paystack-signature' => 'valid_mock_signature',
    ])->assertStatus(200);

    // 4. Verify Persistence
    assertDatabaseHas('webhook_events', [
        'provider' => 'paystack',
        'event_type' => 'charge.success',
    ]);

    $event = WebhookEvent::first();

    // 5. Run the Job
    app(App\Jobs\HandleProviderWebhook::class, ['webhookEventId' => $event->id])->handle(
        app(App\Domains\Ledger\Actions\RecordPaymentLedgerPostings::class)
    );

    // 6. Assert State Changes
    $attempt->refresh();
    expect($attempt->status)->toBe(AuthorizationStatus::Success);

    assertDatabaseHas('transactions', [
        'payment_intent_id' => $payment->id,
        'status' => 'success',
    ]);

    // 7. Assert Ledger Postings
    // 7. Assert Ledger Postings
    /*
     * RecordPaymentLedgerPostings Logic:
     * Provider Clearing: {$provider->id}_{$identifier}_clearing
     * Customer Funds: {$customer->id}_customer_funds
     * Platform Revenue: system_platform_revenue (holder null)
     * Business Wallet: {$business->id}_{$business->id}_wallet (Wait, business wallet slug is manually set in CreateLedgerAccounts)
     */

    // Check slugs based on new logic
    $clearing = LedgerAccount::where('slug', $this->provider->id . '_paystack_clearing')->first();
    $customerFunds = LedgerAccount::where('slug', $payment->customer_id . '_customer_funds')->first();
    $platformRevenue = LedgerAccount::where('slug', 'system_platform_revenue')->first();
    $businessWallet = LedgerAccount::where('slug', $this->business->id . '_wallet')->first();

    expect($clearing)->not->toBeNull();
    expect($customerFunds)->not->toBeNull();

    // Sum of entries for clearing should be (1000 gross - 5 fee) = 995
    expect(LedgerEntry::where('ledger_account_id', $clearing->id)->sum('amount'))->toBe(995);

    // Platform Revenue should be 20 (Customer 10 + Business 10)
    // Recorded as -20 because it's a credit to Revenue account
    expect(LedgerEntry::where('ledger_account_id', $platformRevenue->id)->sum('amount'))->toBe(-20);

    // Business Wallet should be 980 (990 net - 10 fee)
    // Recorded as -980 because it's a credit to Business Wallet (Liability)
    expect(LedgerEntry::where('ledger_account_id', $businessWallet->id)->sum('amount'))->toBe(-980);

    $event->refresh();
    expect($event->processed_at)->not->toBeNull();
});

it('prevents double processing of the same webhook event', function () {
    // For this test we can use fake or just real logic
    $provider = Provider::where('identifier', 'paystack')->first();

    WebhookEvent::create([
        'provider' => 'paystack',
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
        status: 'success',
        rawPayload: []
    ));

    app(App\Domains\Providers\Actions\ProcessWebhook::class)->execute($provider, [
        'event' => 'EVT_1',
        'data' => ['reference' => 'REF_1'],
    ]);

    expect(WebhookEvent::count())->toBe(1);
});
