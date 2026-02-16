<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Ledger;

use App\Domains\Ledger\Services\LedgerService;
use App\Enums\EntryDirection;
use App\Models\Business;
use App\Models\LedgerEntry;
use App\Models\PaymentIntent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ledgerService = app(LedgerService::class);
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Test Business',
        'email' => 'test@business.com',
        'owner_id' => $this->user->id,
    ]);
});

it('atomically updates balances using double entry', function () {
    $debitAccount = $this->ledgerService->businessReceivable($this->business, 'NGN');
    $creditAccount = $this->ledgerService->platformReceivable('NGN');

    $paymentIntent = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'amount_paid' => 1000,
        'currency' => 'NGN',
        'status' => \App\Enums\PaymentStatus::Success,
        'reference' => 'TX_123',
    ]);
    $transaction = $paymentIntent->transaction()->first();

    $batch = $this->ledgerService->startBatch($transaction);

    DB::transaction(function () use ($batch, $transaction, $debitAccount, $creditAccount) {
        $this->ledgerService->post($transaction, $debitAccount, $creditAccount, 1000);
    });

    // Check Ledger Entries
    expect(LedgerEntry::count())->toBe(2);

    $debitEntry = LedgerEntry::where('direction', EntryDirection::DEBIT)->first();
    expect($debitEntry->ledger_account_id)->toBe($debitAccount->id);
    expect($debitEntry->amount)->toBe(1000);

    $creditEntry = LedgerEntry::where('direction', EntryDirection::CREDIT)->first();
    expect($creditEntry->ledger_account_id)->toBe($creditAccount->id);
    expect($creditEntry->amount)->toBe(1000);

    // Check Balance Snapshots
    expect($this->ledgerService->getBalance($debitAccount))->toBe(1000);
    expect($this->ledgerService->getBalance($creditAccount))->toBe(-1000);
});

it('is idempotent via ledger batches', function () {
    $debitAccount = $this->ledgerService->businessReceivable($this->business, 'NGN');
    $creditAccount = $this->ledgerService->platformReceivable('NGN');

    $paymentIntent = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'amount_paid' => 1000,
        'currency' => 'NGN',
        'status' => \App\Enums\PaymentStatus::Success,
        'reference' => 'TX_IDEM',
    ]);
    $transaction = $paymentIntent->transaction()->first();

    $batch = $this->ledgerService->startBatch($transaction);

    // First post
    $this->ledgerService->post($transaction, $debitAccount, $creditAccount, 1000);
    $batch->markPosted();

    // Try second post (should be prevented by developer logic using isPosted)
    // Here we test that if we call post again on same batch, it still adds entries (since post() itself doesn't check batch status, the ACTION does)
    // But we want to ensure the batch unique index works if we try to create another batch.

    $sameBatch = $this->ledgerService->startBatch($transaction);
    expect($sameBatch->id)->toBe($batch->id);
    expect($sameBatch->isPosted())->toBeTrue();
});

it('handles concurrent updates correctly', function () {
    // This is hard to test in a single thread, but we can verify the SQL Upsert works as intended.
    $account = $this->ledgerService->businessReceivable($this->business, 'NGN');

    // Simulate 3 increments
    $this->ledgerService->post(
        PaymentIntent::factory()->create()->transaction,
        $account,
        $this->ledgerService->platformReceivable('NGN'),
        100
    );

    $this->ledgerService->post(
        PaymentIntent::factory()->create()->transaction,
        $account,
        $this->ledgerService->platformReceivable('NGN'),
        200
    );

    expect($this->ledgerService->getBalance($account))->toBe(300);
});

it('supports fluent transaction API via facade', function () {
    $debitAccount = $this->ledgerService->businessReceivable($this->business, 'NGN');
    $creditAccount = $this->ledgerService->platformReceivable('NGN');

    $paymentIntent = PaymentIntent::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'amount_paid' => 1000,
        'currency' => 'NGN',
        'status' => \App\Enums\PaymentStatus::Success,
        'reference' => 'TX_FLUENT',
    ]);
    $transaction = $paymentIntent->transaction()->first();

    \App\Domains\Ledger\Facades\Ledger::transaction($transaction)
        ->entries([
            \App\Domains\Ledger\DataTransferObjects\LedgerEntry::debit($debitAccount, 1000),
            \App\Domains\Ledger\DataTransferObjects\LedgerEntry::credit($creditAccount, 1000),
        ]);

    expect($this->ledgerService->getBalance($debitAccount))->toBe(1000)
        ->and($this->ledgerService->getBalance($creditAccount))->toBe(-1000);
});

it('distinguishes between test and live mode accounts', function () {
    $liveAccount = $this->ledgerService->businessReceivable($this->business, 'NGN', \App\Enums\PaymentMode::Live);
    $testAccount = $this->ledgerService->businessReceivable($this->business, 'NGN', \App\Enums\PaymentMode::Test);

    expect($liveAccount->id)->not->toBe($testAccount->id);
    expect($liveAccount->mode)->toBe(\App\Enums\PaymentMode::Live);
    expect($testAccount->mode)->toBe(\App\Enums\PaymentMode::Test);
});
