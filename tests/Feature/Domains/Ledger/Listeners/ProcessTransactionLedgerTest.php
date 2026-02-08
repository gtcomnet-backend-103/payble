<?php

declare(strict_types=1);

use App\Domains\Ledger\Listeners\ProcessTransactionLedger;
use App\Enums\FeeBearer;
use App\Enums\PaymentChannel;
use App\Enums\TransactionStatus;
use App\Events\TransactionSuccessful;
use App\Models\Business;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->business = Business::create([
        'name' => 'Web Business',
        'email' => 'web@business.com',
        'owner_id' => $this->user->id,
    ]);

    // Create provider manually since factory might not exist or be different
    $this->provider = Provider::create([
        'name' => 'Paystack',
        'identifier' => 'paystack',
        'supported_channels' => ['card'],
        'is_active' => true,
    ]);
});

it('processes ledger postings when transaction is successful with merchant bearer', function () {
    // Arrange
    $transaction = Transaction::create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'gross_amount' => 1000,
        'fees' => 50,
        'currency' => 'NGN',
        'status' => TransactionStatus::Success,
        'channel' => PaymentChannel::Card,
        'mode' => 'live',
        'reference' => 'REF_TX_123',
    ]);

    $event = new TransactionSuccessful(
        transaction: $transaction,
        provider: $this->provider,
        bearer: FeeBearer::Merchant,
        totalFee: 50,
        providerFee: 10
    );

    // Act & Assert
    $this->mock(App\Domains\Ledger\Actions\RecordPaymentLedgerPostings::class, function (MockInterface $mock) use ($transaction) {
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(function ($tx, $prov, $custFee, $bizFee, $provFee) use ($transaction) {
                return $tx->id === $transaction->id
                    && $prov->id === $this->provider->id
                    && $custFee === 0
                    && $bizFee === 50
                    && $provFee === 10;
            });
    });

    $listener = app(ProcessTransactionLedger::class);
    $listener->handle($event);
});

it('processes ledger postings with customer bearer', function () {
    // Arrange
    $transaction = Transaction::create([
        'business_id' => $this->business->id,
        'amount' => 1000,
        'gross_amount' => 1050,
        'fees' => 50,
        'currency' => 'NGN',
        'status' => TransactionStatus::Success,
        'channel' => PaymentChannel::Card,
        'mode' => 'live',
        'reference' => 'REF_TX_456',
    ]);

    $event = new TransactionSuccessful(
        transaction: $transaction,
        provider: $this->provider,
        bearer: FeeBearer::Customer,
        totalFee: 50,
        providerFee: 10
    );

    // Act & Assert
    $this->mock(App\Domains\Ledger\Actions\RecordPaymentLedgerPostings::class, function (MockInterface $mock) use ($transaction) {
        $mock->shouldReceive('execute')
            ->once()
            ->withArgs(function ($tx, $prov, $custFee, $bizFee, $provFee) use ($transaction) {
                return $tx->id === $transaction->id
                    && $custFee === 50
                    && $bizFee === 0
                    && $provFee === 10;
            });
    });

    $listener = app(ProcessTransactionLedger::class);
    $listener->handle($event);
});
