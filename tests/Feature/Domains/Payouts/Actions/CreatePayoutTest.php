<?php

declare(strict_types=1);

use App\Domains\Payouts\Actions\CreatePayout;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Transaction;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->business = Business::factory()->create();
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

    $payout = $this->action->execute($this->business, $data);

    expect($payout)->toBeInstanceOf(Payout::class)
        ->amount->toBe(50000)
        ->currency->toBe(Currency::NGN)
        ->status->toBe(PayoutStatus::Pending)
        ->reference->toBe('PAY_REF_123')
        ->requires_otp->toBeFalse()
        ->metadata->toMatchArray(['foo' => 'bar', 'bank_details' => [
            'bank_code' => '058',
            'account_number' => '0123456789',
            'account_name' => 'John Doe',
        ]]);

    expect($payout->transaction)->toBeInstanceOf(Transaction::class)
        ->amount->toBe(50000) // Transaction doesn't have amount, it has source
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

    $payout = $this->action->execute($this->business, $data);

    expect($payout->requires_otp)->toBeTrue();
});

it('validates required fields', function () {
    $this->action->execute($this->business, []);
})->throws(ValidationException::class);
