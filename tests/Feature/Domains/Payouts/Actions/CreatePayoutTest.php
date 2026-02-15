<?php

declare(strict_types=1);

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
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->admin = Admin::factory()->create();
    $this->bankAccount = BankAccount::factory()->create([
        'business_id' => $this->business->id,
    ]);
    $this->business->bankAccount()->associate($this->bankAccount);
    $this->business->save();
    $this->action = app(CreatePayout::class);
});

it('creates a payout successfully', function () {
    $data = [
        'amount' => 50000,
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
        'amount' => 10000,
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
