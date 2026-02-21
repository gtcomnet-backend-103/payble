<?php

declare(strict_types=1);

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Enums\PaymentMode;
use App\Models\Admin;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\Transaction;
use Illuminate\Support\Str;

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
    $this->ledgerPostingService = app(LedgerPostingServiceInterface::class);
    $this->businessAccount = $this->ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test);

    // Initial earnings
    $yesterday = now()->subDay();
    $this->travelTo($yesterday);

    $earningTx = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 50000,
        'reference' => 'EARN_'.Str::random(10),
        'source_type' => 'payment',
        'source_id' => 1,
    ]);
    $this->ledgerService->post($earningTx, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 50000);

    $this->travelBack();

    $this->action = app(CreatePayout::class);
});

it('does not double count earnings when a payout is reversed', function () {
    $data = [
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'John Doe',
    ];

    // 1. Create first payout
    $payout1 = $this->action->execute($this->business, $this->admin, $data);
    expect($payout1->amount)->toBe(50000);

    // 2. Simulate failure and reversal
    // We need to set a provider so reversal doesn't throw (based on ReversePayoutLedgerPostings logic)
    $payout1->update(['provider_id' => 1]); 
    
    $transaction = $payout1->transaction;
    $this->ledgerPostingService->reverse($transaction);

    // Verify balance is back to normal (should be 50000 available for payout)
    // Actually, balance check in CreatePayout is:
    // $balance = $this->ledgerService->getBalance($account);
    // if ($balance >= 0 || abs($balance) < $requestedAmount) ...
    
    // 3. Try to create payout again for the same date
    // This SHOULD ideally be 50000 again.
    // If it's flawed, it will see 50000 (orig) + 50000 (reversal) = 100000 earnings.
    // And it will fail with "Insufficient balance" because actual balance is 50000.
    
    $payout2 = $this->action->execute($this->business, $this->admin, $data);
    
    expect($payout2->amount)->toBe(50000);
});

it('only pays out remaining earnings if a partial payout was already made', function () {
    $data = [
        'currency' => 'NGN',
        'bank_code' => '058',
        'account_number' => '0123456789',
        'account_name' => 'John Doe',
    ];

    // 1. Initial earnings: 50,000 (from beforeEach)
    
    // 2. Add more earnings for the same day
    $yesterday = now()->subDay();
    $this->travelTo($yesterday);
    $earningTx = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 30000,
        'reference' => 'EARN_2',
        'source_type' => 'payment',
        'source_id' => 2,
    ]);
    $this->ledgerService->post($earningTx, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 30000);
    $this->travelBack();

    // Total earnings for yesterday: 80,000

    // 3. Create a payout (will try to pay 80,000)
    $payout1 = $this->action->execute($this->business, $this->admin, $data);
    expect($payout1->amount)->toBe(80000);

    // 4. Add even MORE earnings for that same day
    $this->travelTo($yesterday);
    $earningTx3 = Transaction::factory()->create([
        'business_id' => $this->business->id,
        'amount' => 20000,
        'reference' => 'EARN_3',
        'source_type' => 'payment',
        'source_id' => 3,
    ]);
    $this->ledgerService->post($earningTx3, $this->ledgerService->platformReceivable('NGN', PaymentMode::Test), $this->businessAccount, 20000);
    $this->travelBack();

    // Now if we calculate earnings, it should be 20,000 (100,000 total - 80,000 already paid)
    // The current flawed logic would see 100,000.
    
    $payout2 = $this->action->execute($this->business, $this->admin, $data);
    expect($payout2->amount)->toBe(20000);
});
