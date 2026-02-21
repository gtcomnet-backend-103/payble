<?php

declare(strict_types=1);

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Domains\Payouts\Actions\ProcessPayout;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\WebhookEvent;
use App\Domains\Webhooks\Jobs\ProcessWebhook;

it('processes a payout webhook successfully', function () {
    $provider = Provider::factory()->create(['identifier' => 'paystack']);
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::Processing,
        'provider_id' => $provider->id,
        'provider_reference' => 'TEST_REF_123',
    ]);

    // Manual transaction creation for the Payout (simulating AuthorizePayout behavior)
    $transaction = Transaction::create([
        'business_id' => $payout->business_id,
        'source_type' => Payout::class,
        'source_id' => $payout->id,
        'reference' => $payout->reference,
        'provider_reference' => 'TEST_REF_123',
        'amount' => $payout->amount,
        'fee' => 0,
        'currency' => $payout->currency,
        'status' => App\Enums\TransactionStatus::Pending,
        'mode' => $payout->mode,
    ]);

    $webhookEvent = WebhookEvent::create([
        'provider' => 'paystack',
        'event_type' => 'transfer.success',
        'raw_payload' => [
            'event' => 'transfer.success',
            'data' => [
                'reference' => 'TEST_REF_123',
                'status' => 'success',
            ],
        ],
        'received_at' => now(),
    ]);

    // Mock dependencies of ProcessPayout
    $this->mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(new \App\Supports\Providers\DataTransferObjects\ProviderResponse(
            status: \App\Enums\AuthorizationStatus::Success,
            providerReference: 'TEST_REF_123',
            rawResponse: ['status' => 'success']
        ));

    $this->mock(LedgerPostingServiceInterface::class)
        ->shouldReceive('postTransaction')
        ->once();

    $job = new ProcessWebhook($webhookEvent->id);
    $job->handle();

    $webhookEvent->refresh();
    expect($webhookEvent->processed_at)->not->toBeNull();
    expect($webhookEvent->feedback)->toBe('event processed successfully');
});
