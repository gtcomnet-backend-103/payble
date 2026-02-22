<?php

declare(strict_types=1);

namespace Tests\Feature\Domains\Advances;

use App\Domains\Ledger\Facades\Ledger;
use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Domains\Payouts\DataTransferObjects\BankAccountDetails;
use App\Enums\AccountType;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\PayoutType;
use App\Models\Business;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Mockery\MockInterface;
use Tests\TestCase;

class AdvanceUnifiedFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->business = Business::create([
            'name' => 'Test Business',
            'email' => 'test@business.com',
            'owner_id' => $this->user->id,
            'advance_threshold_percentage' => 50, // 50% threshold
        ]);
        $this->user->businesses()->attach($this->business);
        Sanctum::actingAs($this->business, ['*'], 'business');

        config(['app.payment_mode' => 'test']);

        // Create a provider
        Provider::create([
            'name' => 'Test Provider',
            'identifier' => 'test-provider',
            'is_active' => true,
            'is_payout_enabled' => true,
            'mode' => 'test',
            'supported_channels' => ['test'],
        ]);

        \App\Models\FeeConfig::create([
            'channel' => \App\Enums\FeeChannel::Payout->value,
            'fixed_amount' => 1000, // 10 NGN
            'is_active' => true,
        ]);

        $this->mock(BankAccountResolver::class, function (MockInterface $mock) {
            $mock->shouldReceive('resolveAccount')
                ->andReturn(new BankAccountDetails('Test Account', '0000000000', '000'));
        });
    }

    public function test_cannot_initiate_advance_above_threshold(): void
    {
        // 1. Give business some RECEIVABLE balance
        $receivable = Ledger::receivable($this->business, 'NGN', PaymentMode::Test);
        Ledger::issueInternalCredit($receivable, 100000); // 1,000 NGN earnings

        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        // 2. Try to initiate advance for 600 NGN (Threshold is 500 NGN)
        $response = $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 60000,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount'])
            ->assertJsonFragment(['message' => 'Requested amount exceeds your advance limit of 50000.']);

        // Check specifically for our custom message if needed, but above covers the failure.
    }

    public function test_can_initiate_advance_and_verify_ledger(): void
    {
        // 1. Give business 2,000 NGN earnings
        $receivable = Ledger::receivable($this->business, 'NGN', PaymentMode::Test);
        Ledger::issueInternalCredit($receivable, 200000);

        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        // 2. Initiate 500 NGN advance
        $response = $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 50000,
        ]);

        $response->assertStatus(201);
        $payoutReference = $response->json('data.reference');

        $this->assertDatabaseHas('payouts', [
            'reference' => $payoutReference,
            'type' => PayoutType::Advance->value,
        ]);

        // 3. Verify Ledger
        $advanceAccount = Ledger::advance($this->business, 'NGN', PaymentMode::Test);
        $revenue = Ledger::platformRevenue('NGN', PaymentMode::Test);
        $platformCash = Ledger::platformReceivable('NGN', PaymentMode::Test);

        $this->assertEquals(50000, Ledger::getBalance($advanceAccount)); // Business owes 500 NGN
        $this->assertEquals(-1000, Ledger::getBalance($revenue)); // 10 NGN fee (Net Disburse logic)
        $this->assertEquals(-49000, Ledger::getBalance($platformCash)); // Platform sent 490 NGN (Credit)
    }

    public function test_automatic_settlement_on_standard_payout(): void
    {
        // 1. Setup an existing advance debt of 500 NGN
        $receivable = Ledger::receivable($this->business, 'NGN', PaymentMode::Test);
        $advanceAccount = Ledger::advance($this->business, 'NGN', PaymentMode::Test);

        // Manual internal fix for setup: simulate debt
        $this->executeInitialAdvance($receivable, $advanceAccount, 50000);

        // 2. Now trigger a standard payout of 1,000 NGN (Settlement of earnings)
        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        // We create it manually as if system triggered it
        $payout = $bankAccount->payouts()->create([
            'business_id' => $this->business->id,
            'originator_id' => $this->user->id,
            'originator_type' => User::class,
            'amount' => 100000,
            'fee' => 1000,
            'currency' => 'NGN',
            'type' => PayoutType::Payout,
            'reference' => 'SETTLE-123',
            'status' => PayoutStatus::Pending,
            'mode' => PaymentMode::Test,
        ]);

        // Record reservation (Holding)
        app(\App\Domains\Payouts\Contracts\LedgerPostingServiceInterface::class)->reserve(
            app(\App\Domains\Payouts\Contracts\LedgerPostingServiceInterface::class)->recordTransaction($payout)
        );

        // 3. Authorize it. It should detect debt and disburse only REMAINDER.
        // Net disbursement should be (1000 - 10 fee) - 500 debt = 490 NGN.
        $this->postJson("/api/transfers/SETTLE-123/authorize")->assertStatus(200);

        // 4. Verify Ledger after Success
        $this->assertEquals(0, Ledger::getBalance($advanceAccount)); // Debt cleared
        $this->assertEquals(-100000, Ledger::getBalance($receivable)); // 200k - 100k payout = 100k left (Credit)

        // Check final disburse amount in provider account
        $provider = Provider::where('identifier', 'test-provider')->first();
        $providerAccount = Ledger::providerReceivable($provider, 'NGN', PaymentMode::Test);
        $this->assertEquals(-49000, Ledger::getBalance($providerAccount)); // Only 490 sent to bank (Credit)
    }

    private function executeInitialAdvance($receivable, $advanceAccount, $amount): void
    {
        Ledger::issueInternalCredit($receivable, 200000); // Give earnings

        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => $amount,
        ])->assertStatus(201);
    }
}
