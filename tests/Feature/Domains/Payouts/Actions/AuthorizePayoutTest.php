<?php

declare(strict_types=1);

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Events\Payouts\PayoutAuthorized;
use App\Exceptions\PayoutException;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->admin = Admin::factory()->create();
    $this->provider = Provider::factory()->create([
        'name' => 'Test Provider',
        'is_active' => true,
        'mode' => PaymentMode::Test,
        'is_payout_enabled' => true,
        'identifier' => 'paystack',
    ]);
    $this->bankAccount = BankAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);
    $this->business->bankAccount()->associate($this->bankAccount);
    $this->business->save();

    // Fund business account with sufficient balance
    $ledgerService = app(LedgerService::class);
    $businessAccount = $ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test);

    // Create a dummy transaction for funding
    $fundingTx = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000000, // 1M kobo = 10,000 NGN
        'reference' => 'FUND_TEST',
        'source_type' => 'funding',
        'source_id' => $this->business->id,
    ]);

    // Post funding entry
    $platformClearing = $ledgerService->platformReceivable('NGN', PaymentMode::Test);
    $batch = $ledgerService->startBatch($fundingTx, 'test_funding');
    $ledgerService->post($batch, $fundingTx, $businessAccount, $platformClearing, 1000000);

    $this->disbursementProvider = $this->mock(\App\Domains\Payouts\Contracts\DisbursementProviderInterface::class);
    $this->disbursementProvider->shouldReceive('provider')->andReturn($this->provider)->byDefault();

    $this->createPayout = app(CreatePayout::class);
    $this->authorizePayout = app(AuthorizePayout::class);
});

it('authorizes a payout successfully in test mode', function () {
    Event::fake([PayoutAuthorized::class]);

    // Create payout
    $payout = $this->createPayout->execute($this->business, $this->admin, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
    ]);

    // CreatePayout sets status to Pending
    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Pending);

    // Authorize payout
    $this->disbursementProvider->shouldReceive('transfer')
        ->once()
        ->andReturn(new \App\Supports\Providers\DataTransferObjects\ProviderResponse(
            status: \App\Enums\AuthorizationStatus::Success,
            providerReference: 'PROV_REF_123'
        ));

    $this->authorizePayout->execute($payout);

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Processing);
    expect($payout->provider_id)->toBe($this->provider->id);
    expect($payout->provider_reference)->not->toBeNull();
});

it('validates payout status before authorization', function () {
    Event::fake();

    $payout = $this->createPayout->execute($this->business, $this->admin, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
    ]);

    // Status is already Pending, but AuthorizePayout checks if it's NOT Pending to throw exception for "already processed"
    $payout->update(['status' => PayoutStatus::Processing]);

    expect(fn() => $this->authorizePayout->execute($payout))
        ->toThrow(PayoutException::class, 'this payout cannot be authorized in this state');
});

it('handles authorization failure gracefully', function () {
    $payout = $this->createPayout->execute($this->business, $this->admin, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
    ]);

    $payout->refresh();

    // Mock DisbursementProviderInterface instead of ProviderResolver
    $mockP = Mockery::mock(\App\Domains\Payouts\Contracts\DisbursementProviderInterface::class);
    $mockP->shouldReceive('provider')->andReturn($this->provider);
    $mockP->shouldReceive('transfer')->andThrow(new Exception('Provider failure'));
    $this->instance(\App\Domains\Payouts\Contracts\DisbursementProviderInterface::class, $mockP);

    try {
        $this->authorizePayout->execute($payout);
    } catch (Throwable $e) {
        // Expected to fail
    }

    $payout->refresh();

    expect($payout->status)->toBe(PayoutStatus::Failed);
});

it('prevents concurrent authorization attempts', function () {
    Event::fake([PayoutAuthorized::class]);

    $payout = $this->createPayout->execute($this->business, $this->admin, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
    ]);

    $payout->refresh();

    // First authorization
    $this->disbursementProvider->shouldReceive('transfer')
        ->once()
        ->andReturn(new \App\Supports\Providers\DataTransferObjects\ProviderResponse(
            status: \App\Enums\AuthorizationStatus::Success,
            providerReference: 'PROV_REF_CONC'
        ));

    $this->authorizePayout->execute($payout);

    $payout->refresh();

    // Second authorization should fail
    expect(fn() => $this->authorizePayout->execute($payout))
        ->toThrow(PayoutException::class, 'this payout cannot be authorized in this state');
});
