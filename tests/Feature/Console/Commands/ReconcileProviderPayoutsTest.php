<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Domains\Payouts\Actions\ProcessPayout;
use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\Provider;
use App\Models\Transaction;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('reconciles payouts in non-final statuses', function () {
    // 1. Setup payouts in different statuses
    $provider = Provider::factory()->create();
    $processing = Payout::factory()->create(['status' => PayoutStatus::Processing, 'provider_id' => $provider->id, 'provider_reference' => 'REF1']);
    $unknown = Payout::factory()->create(['status' => PayoutStatus::Unknown, 'provider_id' => $provider->id, 'provider_reference' => 'REF2']);
    $reconciliationRequired = Payout::factory()->create(['status' => PayoutStatus::ReconciliationRequired, 'provider_id' => $provider->id, 'provider_reference' => 'REF3']);
    $success = Payout::factory()->create(['status' => PayoutStatus::Success, 'provider_id' => $provider->id, 'provider_reference' => 'REF4']);

    // 2. Mock dependencies
    $this->mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')
        ->times(3)
        ->andReturn(
            new ProviderResponse(status: AuthorizationStatus::Pending, providerReference: 'REF1'),
            new ProviderResponse(status: AuthorizationStatus::Pending, providerReference: 'REF2'),
            new ProviderResponse(status: AuthorizationStatus::Pending, providerReference: 'REF3')
        );

    // 3. Run the command
    Artisan::call('payouts:reconcile');

    // 4. Verification is done by the shouldReceive('verify')->times(3)
});

it('resolves payout status correctly during reconciliation', function () {
    $provider = Provider::factory()->create();
    $payout = Payout::factory()->create([
        'status' => PayoutStatus::ReconciliationRequired,
        'provider_id' => $provider->id,
        'provider_reference' => 'TEST_REF'
    ]);

    // Create a transaction for the payout
    Transaction::factory()->create([
        'source_type' => Payout::class,
        'source_id' => $payout->id,
        'business_id' => $payout->business_id,
        'provider_reference' => 'TEST_REF',
    ]);

    // Mock dependencies to trigger Success
    $this->mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')
        ->once()
        ->andReturn(new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: 'TEST_REF',
            rawResponse: ['status' => 'success']
        ));

    $this->mock(LedgerPostingServiceInterface::class)
        ->shouldReceive('postTransaction')
        ->once();

    $this->artisan('payouts:reconcile')
        ->expectsOutputToContain("Payout {$payout->id} resolved to Success.")
        ->assertExitCode(0);

    expect($payout->refresh()->status)->toBe(PayoutStatus::Success);
});
