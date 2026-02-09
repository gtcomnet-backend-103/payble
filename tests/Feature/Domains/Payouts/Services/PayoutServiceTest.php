<?php

declare(strict_types=1);

use App\Domains\Payouts\Services\PayoutService;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\TransactionStatus;
use App\Models\Business;
use App\Models\Payout;
use App\Models\Provider;

beforeEach(function () {
    $this->business = Business::factory()->create();
    $this->service = app(PayoutService::class);

    // Setup configured provider for test mode
    $this->provider = Provider::factory()->create([
        'mode' => PaymentMode::Test,
        'is_payout_enabled' => true,
        'identifier' => 'paystack', // or whatever is default/mock
        'supported_channels' => ['bank_transfer'],
    ]);
});

it('processes a payout successfully in test mode', function () {
    // 1. Create Payout
    $data = [
        'amount' => 5000,
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'Test User',
        'reference' => 'PAY_SVC_001',
    ];

    $payout = $this->service->create($this->business, $data);

    expect($payout)->toBeInstanceOf(Payout::class)
        ->status->toBe(PayoutStatus::Pending);

    // 2. Process Payout
    $processed = $this->service->process($payout);

    expect($processed->status)->toBe(PayoutStatus::Completed);
    expect($processed->provider_id)->toBe($this->provider->id);

    // Check Ledger Entries (Indirectly via transaction status or specific ledger query if needed)
    expect($processed->transaction->status)->toBe(TransactionStatus::Success);

    // Verify Ledger Entries exist
    // expecting 2 entries: Debit Business, Credit Provider
    // Then 2 reversal entries? No, success means money moved.
    // Actually, `RecordPayoutLedgerPostings` does Debit Business, Credit Provider.
    // If successful, they stay.

    $entries = $processed->transaction->ledgerEntries;
    expect($entries)->toHaveCount(2);
});

it('fails if otp is required but invalid', function () {
    $data = [
        'amount' => 5000,
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'Test User',
        'requires_otp' => true,
    ];

    $payout = $this->service->create($this->business, $data);

    // Wrong OTP
    expect(fn () => $this->service->process($payout, '000000'))
        ->toThrow(RuntimeException::class, 'Invalid OTP');

    // Correct OTP
    $processed = $this->service->process($payout, '123456');
    expect($processed->status)->toBe(PayoutStatus::Completed);
});
