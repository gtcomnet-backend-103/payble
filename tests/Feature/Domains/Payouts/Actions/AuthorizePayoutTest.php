<?php

declare(strict_types=1);

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Events\Payouts\PayoutAuthorized;
use App\Exceptions\PayoutProcessingException;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->provider = Provider::factory()->create([
        'name' => 'Test Provider',
        'is_active' => true,
        'mode' => PaymentMode::Test,
        'is_payout_enabled' => true,
    ]);
    $this->bankAccount = BankAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);

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

    $this->createPayout = app(CreatePayout::class);
    $this->authorizePayout = app(AuthorizePayout::class);
});

it('authorizes a payout successfully in test mode', function () {
    Event::fake([PayoutAuthorized::class]);

    // Create payout
    $payout = $this->createPayout->execute($this->bankAccount, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
        'mode' => PaymentMode::Test->value,
    ]);

    // Wait for PayoutCreated event to process
    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::ReadyForProcessing);

    // Authorize payout
    $authorized = $this->authorizePayout->execute($payout); // returns void now? No, wait. execute returns void?
    // Let's check AuthorizePayout.php: public function execute(Payout $payout): void
    // So $authorized is null.

    $payout->refresh();
    expect($payout->status)->toBe(PayoutStatus::Processing);
    expect($payout->provider_id)->toBe($this->provider->id);
    expect($payout->provider_reference)->not->toBeNull();
    // In simulateTestMode, provider reference is set? DisbursmentProvider::disburse?
    // Wait, check AuthorizePayout logic.
    // Provider::provide(Test) -> returns provider.
    // DisbursmentProvider::disburse -> updates provider_reference? NO.
    // AuthorizePayout updates provider_reference AFTER disburse using disbursementData->reference.
    // For test mode, does PayoutTransferData use simulated ref?
    // ProviderResolver resolves Test provider.
    // Adapter returns success?
    // If successful, AuthorizePayout updates p->provider_reference = disbursementData->reference.
    // But disbursementData->reference is Payout->reference.
    // So provider_reference == Payout->reference?
    // Let's check logic.

    // Event should be fired
    Event::assertDispatched(PayoutAuthorized::class, function ($event) use ($payout) {
        return $event->payoutId === $payout->id;
    });
});

it('validates payout status before authorization', function () {
    Event::fake();

    $payout = $this->createPayout->execute($this->bankAccount, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
        'mode' => PaymentMode::Test->value,
    ]);

    // Don't wait for event processing - status is still Pending
    expect(fn() => $this->authorizePayout->execute($payout))
        ->toThrow(PayoutProcessingException::class, 'Payout already processed or in progress'); // Or similar from "ReadyForProcessing" check
    // Code: "Payout already processed or in progress. Status: pending"
});

it('handles authorization failure gracefully', function () {
    $payout = $this->createPayout->execute($this->bankAccount, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
        'mode' => PaymentMode::Live->value,
    ]);

    $payout->refresh();

    $payout->refresh();

    // Mock ProviderResolver to throw exception
    $mockResolver = Mockery::mock(\App\Domains\Payments\Providers\Services\ProviderResolver::class);
    $mockResolver->shouldReceive('resolve')->andThrow(new Exception('Provider failure'));
    $this->instance(\App\Domains\Payments\Providers\Services\ProviderResolver::class, $mockResolver);

    try {
        $this->authorizePayout->execute($payout);
    } catch (Throwable $e) {
        // Expected to fail
    }

    $payout->refresh();

    // Status should be Failed
    expect($payout->status)->toBe(PayoutStatus::Failed);
});

it('prevents concurrent authorization attempts', function () {
    Event::fake([PayoutAuthorized::class]);

    $payout = $this->createPayout->execute($this->bankAccount, [
        'amount' => 10000,
        'currency' => Currency::NGN->value,
        'mode' => PaymentMode::Test->value,
    ]);

    $payout->refresh();

    // First authorization
    $this->authorizePayout->execute($payout);

    $payout->refresh();

    // Second authorization should fail
    expect(fn() => $this->authorizePayout->execute($payout))
        ->toThrow(PayoutProcessingException::class, 'Payout already processed or in progress');
});
