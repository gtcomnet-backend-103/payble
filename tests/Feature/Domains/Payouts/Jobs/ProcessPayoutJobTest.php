<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Payouts\Jobs;

use App\Domains\Payouts\Actions\ProcessPayout;
use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Jobs\ProcessPayoutJob;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Provider;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Enums\AuthorizationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Exception;

uses(RefreshDatabase::class);

it('has predefined exponential backoff intervals', function () {
    $job = new ProcessPayoutJob(Payout::factory()->create());

    expect($job->backoff())->toBe([
        300, // 5 mins
        900, // 15 mins
        1800, // 30 mins
        3600, // 1 hour
        10800, // 3 hours
        21600, // 6 hours
    ]);
});

it('touches the payout and releases back to queue if still processing', function () {
    Carbon::setTestNow(now());
    $provider = Provider::factory()->create();
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::Processing,
        'provider_id' => $provider->id,
        'provider_reference' => 'TEST_REF_123',
    ]);
    $initialUpdatedAt = $payout->updated_at;

    // Move time forward
    Carbon::setTestNow(now()->addMinute());

    $this->mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(new ProviderResponse(
            status: AuthorizationStatus::Pending,
            providerReference: 'TEST_REF_123',
            rawResponse: []
        ));

    $processAction = app(ProcessPayout::class);

    $job = new ProcessPayoutJob($payout);
    $job->handle($processAction);

    expect($payout->refresh()->updated_at->gt($initialUpdatedAt))->toBeTrue();
    expect($payout->status)->toBe(PayoutStatus::Processing);
});

it('transitions to reconciliation_required after max attempts', function () {
    $provider = Provider::factory()->create();
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::Processing,
        'provider_id' => $provider->id,
        'provider_reference' => 'TEST_REF_123',
    ]);

    $this->mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(new ProviderResponse(
            status: AuthorizationStatus::Pending,
            providerReference: 'TEST_REF_123',
            rawResponse: []
        ));

    $processAction = app(ProcessPayout::class);

    $job = new ProcessPayoutJob($payout);

    // Simulate being on the last attempt
    $job = Mockery::mock(ProcessPayoutJob::class, [$payout])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $job->shouldReceive('attempts')->andReturn(7);

    $job->handle($processAction);

    expect($payout->refresh()->status)->toBe(PayoutStatus::ReconciliationRequired);
});

it('transitions to unknown status on job failure', function () {
    $payout = Payout::factory()->create(['status' => PayoutStatus::Processing]);

    $job = new ProcessPayoutJob($payout);
    $job->failed(new Exception('Terminal failure'));

    expect($payout->refresh()->status)->toBe(PayoutStatus::Unknown);
});
