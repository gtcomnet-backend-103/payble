<?php

declare(strict_types=1);

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\TransactionStatus;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->admin = Admin::factory()->create();
    $this->bankAccount = BankAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);
    $this->bankAccount->business()->associate($this->business);
    $this->bankAccount->save();

    config(['app.payment_mode' => 'test']);

    $this->ledgerService = app(LedgerService::class);
    $this->businessAccount = $this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test);

    // Fund account for general balance check - using internal credit (no transaction)
    $this->ledgerService->issueInternalCredit($this->businessAccount, 1000000);

    // Seed earnings for "yesterday"
    $yesterday = now()->subDay();
    $this->travelTo($yesterday);

    $earningTx = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 50000,
        'reference' => 'EARN_' . Str::random(10),
        'source_type' => \App\Models\PaymentIntent::class,
        'source_id' => 1,
    ]);
    $this->ledgerService->post($earningTx, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 50000);

    $this->travelBack();

    $this->action = app(CreatePayout::class);
});

it('creates a payout successfully using daily earnings', function () {
    $data = [
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'John Doe',
        'reference' => 'PAY_REF_123',
        'metadata' => ['foo' => 'bar'],
    ];

    $payout = $this->action->execute($this->business, $this->admin, $data);

    expect($payout)->toBeInstanceOf(Payout::class)
        ->amount->toBe(50000)
        ->currency->toBe(Currency::NGN)
        ->status->toBe(PayoutStatus::Pending)
        ->reference->toBe('PAY_REF_123')
        ->requires_otp->toBeFalse()
        ->metadata->toMatchArray(['foo' => 'bar', 'account' => [
            'bank_code' => $this->bankAccount->bank_code,
            'account_number' => $this->bankAccount->account_number,
            'account_name' => $this->bankAccount->account_name,
        ]]);

    expect($payout->transaction)->toBeInstanceOf(Transaction::class)
        ->amount->toBe(50000)
        ->fee->toBeGreaterThanOrEqual(0)
        ->status->toBe(TransactionStatus::Pending)
        ->mode->toBe(PaymentMode::Test); // Default to test
});

it('sets requires_otp flag correctly', function () {
    $data = [
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'Jane Doe',
        'requires_otp' => true,
    ];

    $payout = $this->action->execute($this->business, $this->admin, $data);

    expect($payout->requires_otp)->toBeTrue();
});

it('validates required fields', function () {
    $this->action->execute($this->business, $this->admin, []);
})->throws(ValidationException::class);

it('throws exception when no earnings are found for the date', function () {
    $data = [
        'date' => now()->format('Y-m-d'), // Today has no earnings (just funding)
        'currency' => 'NGN',
    ];

    $this->action->execute($this->business, $this->admin, $data);
})->throws(ValidationException::class, 'No earnings found for the specified date');

it('calculates payout amount correctly for a specific date', function () {
    $specificDate = now()->subDays(5);
    $this->travelTo($specificDate);

    // Seed 3 earnings for the same date
    for ($i = 0; $i < 3; $i++) {
        $earningTx = Transaction::factory()->create([
            'business_id' => $this->business->id,
            'amount' => 10000,
            'reference' => "EARN_SPECIFIC_{$i}",
            'source_type' => \App\Models\PaymentIntent::class,
            'source_id' => $i + 100,
        ]);
        $this->ledgerService->post($earningTx, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 10000);
    }

    $this->travelBack();

    $payout = $this->action->execute($this->business, $this->admin, [
        'date' => $specificDate->format('Y-m-d'),
        'currency' => 'NGN',
    ]);

    expect($payout->amount)->toBe(30000);
});

it('supports multiple payouts for the same date when earnings are added in between', function () {
    $date = now()->format('Y-m-d');

    // 1. First earning
    $earning1 = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 10000,
        'reference' => 'EARN_MULTIPLE_1',
        'source_type' => \App\Models\PaymentIntent::class,
        'source_id' => 1,
    ]);
    $this->ledgerService->post($earning1, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 10000);

    // 2. First payout
    $payout1 = $this->action->execute($this->business, $this->admin, [
        'date' => $date,
        'currency' => 'NGN',
    ]);
    expect($payout1->amount)->toBe(10000);

    // 3. Second earning for same date
    $earning2 = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 20000,
        'reference' => 'EARN_MULTIPLE_2',
        'source_type' => \App\Models\PaymentIntent::class,
        'source_id' => 2,
    ]);
    $this->ledgerService->post($earning2, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 20000);

    // 4. Second payout for same date
    $payout2 = $this->action->execute($this->business, $this->admin, [
        'date' => $date,
        'currency' => 'NGN',
    ]);
    expect($payout2->amount)->toBe(20000);
});
