<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Domains\Payouts\DataTransferObjects\BankAccountDetails;
use App\Domains\Ledger\Facades\Ledger;
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

class LedgerTransferTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Business $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->business = Business::factory()->create([
            'name' => 'Test Business',
            'email' => 'test@business.com',
            'owner_id' => $this->user->id,
            'advance_threshold_percentage' => 100,
        ]);
        $this->user->businesses()->attach($this->business);
        Sanctum::actingAs($this->business, ['*'], 'business');

        config(['app.payment_mode' => 'test']);

        // Create a provider for testing
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

    public function test_can_get_bank_list(): void
    {
        $response = $this->getJson('/api/utils/banks');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['name', 'code']]]);
    }

    public function test_can_register_recipient(): void
    {
        // Mocking the bank resolve is a bit tricky as it happens in the action.
        // For TestAdapter, it might not be implemented, or could be stubbed.

        $response = $this->postJson('/api/recipients', [
            'account_number' => '0000000000',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.account_number', '0000000000');

        $this->assertDatabaseHas('bank_accounts', [
            'business_id' => $this->business->id,
            'account_number' => '0000000000',
        ]);
    }

    public function test_cannot_initiate_transfer_with_insufficient_balance(): void
    {
        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        $response = $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100000, // 1000 NGN
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_can_initiate_and_authorize_transfer(): void
    {
        // 1. Give business some balance
        $account = Ledger::receivable($this->business, 'NGN', PaymentMode::Test);
        // We want a negative balance because debt means we owe the business.
        // ledgerService->issueInternalCredit actually DECREMENTS the snapshot for CREDIT.
        // Wait, let's check LedgerService again.
        // LedgerEntry::CREDIT, incrementSnapshot($account, -$amount, ...)
        // So internal credit makes the balance more negative. Correct.
        Ledger::issueInternalCredit($account, 500000); // 5000 NGN

        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '0000000000',
            'account_name' => 'Test Account',
            'bank_code' => '000',
            'currency' => 'NGN',
        ]);

        // 2. Initiate
        $initiateResponse = $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100000,
        ]);

        $initiateResponse->assertStatus(201);
        $reference = $initiateResponse->json('data.reference');

        $this->assertDatabaseHas('payouts', [
            'reference' => $reference,
            'type' => PayoutType::Advance->value,
            'status' => PayoutStatus::Pending->value,
        ]);

        // Check ledger advance debt
        $advanceAccount = Ledger::advance($this->business, 'NGN', PaymentMode::Test);
        $this->assertEquals(100000, Ledger::getBalance($advanceAccount)); // Business owes 1,000 NGN

        // 3. Authorize
        $authResponse = $this->postJson("/api/transfers/{$reference}/authorize");

        $authResponse->assertStatus(200)
            ->assertJsonPath('data.status', PayoutStatus::Success->value);

        // Check ledger is still in hold state (Reservation)
        // Wait, for Advances, debt is already recorded.
        // So we check that Advance balance still exists
        $this->assertEquals(100000, Ledger::getBalance($advanceAccount));

        // Check provider clearing account
        $provider = Provider::where('identifier', 'test-provider')->first();
        $providerAccount = Ledger::providerReceivable($provider, 'NGN', PaymentMode::Test);
        $this->assertEquals(-99000, Ledger::getBalance($providerAccount)); // Amount - Fee
    }

    public function test_ledger_is_reversed_on_failed_authorization(): void
    {
        // 1. Give business some balance
        $account = Ledger::receivable($this->business, 'NGN', PaymentMode::Test);
        Ledger::issueInternalCredit($account, 500000); // 5000 NGN

        $bankAccount = $this->business->bankAccounts()->create([
            'account_number' => '1111111111',
            'account_name' => 'Failed Account',
            'bank_code' => '999',
            'currency' => 'NGN',
        ]);

        // 2. Initiate
        $initiateResponse = $this->postJson('/api/transfers', [
            'bank_account_id' => $bankAccount->id,
            'amount' => 100000,
        ]);
        $reference = $initiateResponse->json('data.reference');

        // 3. Mock provider failure and Authorize
        // Since I'm using the real DisbursementProvider which resolves adapters,
        // and TestAdapter returns Success by default, I might need to mock the service or adapter.

        // Actually, TestAdapter has a simple behavior. I'll mock DisbursementProvider for this test.
        $this->mock(\App\Domains\Payouts\Contracts\DisbursementProviderInterface::class, function (MockInterface $mock) use ($reference) {
            $mock->shouldReceive('provider')->andReturn(Provider::first());
            $mock->shouldReceive('transfer')->andReturn(new \App\Supports\Providers\DataTransferObjects\ProviderResponse(
                status: \App\Enums\AuthorizationStatus::Failed,
                providerReference: 'FAILED-REF',
            ));
        });

        $this->postJson("/api/transfers/{$reference}/authorize");

        // 4. Verify ledger reversal
        // Advance debt should be 0 again
        $advanceAccount = Ledger::advance($this->business, 'NGN', PaymentMode::Test);
        $this->assertEquals(0, Ledger::getBalance($advanceAccount));

        // Receivable account should be back to -500,000
        $this->assertEquals(-500000, Ledger::getBalance($account));

        // Provider account should be 0
        $provider = Provider::where('identifier', 'test-provider')->first();
        $providerAccount = Ledger::providerReceivable($provider, 'NGN', PaymentMode::Test);
        $this->assertEquals(0, Ledger::getBalance($providerAccount));
    }
}
