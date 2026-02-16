<?php

declare(strict_types=1);

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Domains\Payouts\Actions\ProcessPayout;
use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Enums\AuthorizationStatus;
use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\FeeConfig;
use App\Models\Provider;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;

use function Pest\Laravel\mock;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->admin = Admin::factory()->create();

    // Create and associate bank account correctly for BelongsTo
    $bankAccount = BankAccount::create([
        'business_id' => $this->business->id,
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'Test Account',
        'currency' => Currency::NGN->value,
    ]);
    $this->business->bankAccount()->associate($bankAccount);
    $this->business->save();
    $this->bankAccount = $bankAccount;

    FeeConfig::factory()->create([
        'business_id' => null,
        'channel' => PaymentChannel::BankTransfer->value,
        'currency' => Currency::NGN->value,
        'percentage' => 1.0, // 1%
        'fixed_amount' => 5000, // 50.00
        'min_fee' => 10000, // 100.00
        'max_fee' => 500000, // 5000.00
        'is_active' => true,
    ]);

    Context::add('business_id', $this->business->id);

    // Ensure we have a provider
    Provider::factory()->create([
        'identifier' => 'paystack',
        'is_payout_enabled' => true,
        'is_active' => true,
        'mode' => PaymentMode::Test,
    ]);

    $this->ledgerService = app(LedgerService::class);
});

it('completes the full payout lifecycle', function () {
    // 1. Setup: Seed Business Wallet with 1,000,000 units (10,000.00)
    $initialAmount = 1000000;
    $this->ledgerService->issueInternalCredit($this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test), $initialAmount);

    // 2. Create Payout (Draft -> Pending)
    /** @var CreatePayout $createAction */
    $createAction = app(CreatePayout::class);
    $payoutAmount = 100000; // 1,000.00

    $payout = $createAction->execute($this->business, $this->admin, [
        'amount' => $payoutAmount,
        'currency' => Currency::NGN->value,
        'reference' => 'PAY-'.Str::random(10),
    ]);

    expect($payout->status)->toBe(PayoutStatus::Pending)
        ->and($payout->amount)->toBe($payoutAmount)
        ->and($payout->fee)->toBe(10000); // 1% of 100000 is 1000, but min_fee is 10000

    // Verify funds reserved (Available: 1,000,000 - 100,000 = 900,000)
    expect($this->ledgerService->getBalance($this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test)))->toBe(900000);

    // 3. Authorize Payout (Pending -> Processing)
    // Mock provider transfer
    mock(DisbursementProviderInterface::class)
        ->shouldReceive('provider')->andReturn(Provider::first())
        ->shouldReceive('transfer')->once()->andReturn(new ProviderResponse(AuthorizationStatus::Pending, 'PRV-123'));

    /** @var AuthorizePayout $authAction */
    $authAction = app(AuthorizePayout::class);
    $payout = $authAction->execute($payout);

    expect($payout->status)->toBe(PayoutStatus::Processing);

    // 4. Process Payout (Processing -> Success)
    $payout->refresh(); // Load provider relationship for verify()
    // Mock provider verification
    mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')->once()->andReturn(new ProviderResponse(AuthorizationStatus::Success, 'PRV-123'));

    /** @var ProcessPayout $processAction */
    $processAction = app(ProcessPayout::class);
    $payout = $processAction->execute($payout);

    expect($payout->status)->toBe(PayoutStatus::Success);

    // Verify ledger finalized (Holds should be empty for this specific transaction)
    // Actually, balance checks are easier on available wallet
    expect($this->ledgerService->getBalance($this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test)))->toBe(900000);
});

it('reverses funds on payout failure', function () {
    // 1. Setup Balance
    $initialAmount = 1000000;
    $this->ledgerService->issueInternalCredit(
        $this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test),
        $initialAmount
    );

    $payoutAmount = 100000;
    /** @var CreatePayout $createAction */
    $createAction = app(CreatePayout::class);
    $payout = $createAction->execute($this->business, $this->admin, [
        'amount' => $payoutAmount,
        'currency' => Currency::NGN->value,
    ]);


    // Transition to Processing
    $payout->update(['status' => PayoutStatus::Processing, 'provider_id' => Provider::first()->id]);

    // 2. Fail the payout
    mock(DisbursementProviderInterface::class)
        ->shouldReceive('verify')->andReturn(new ProviderResponse(AuthorizationStatus::Failed, 'PRV-FAIL'));

    /** @var ProcessPayout $processAction */
    $processAction = app(ProcessPayout::class);
    $payout = $processAction->execute($payout);

    expect($payout->status)->toBe(PayoutStatus::Failed)
        ->and($this->ledgerService->getBalance(
            $this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test)
        ))
        ->toBe(1000000);
});
