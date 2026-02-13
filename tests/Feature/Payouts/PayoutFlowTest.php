<?php

declare(strict_types=1);

namespace Tests\Feature\Payouts;

use App\Domains\Ledger\Services\LedgerService;
use App\Domains\Payouts\Actions\AuthorizePayout;
use App\Domains\Payouts\Actions\CreatePayout;
use App\Domains\Payouts\Actions\ProcessPayout;
use App\Domains\Payouts\Actions\VerifyPayoutOtp;
use App\Enums\AccountType;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Models\AuthorizationAttempt;
use App\Models\BankAccount;
use App\Models\Business;
use App\Models\FeeConfig;
use App\Models\Provider;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PayoutFlowTest extends TestCase
{
    use RefreshDatabase;

    private Business $business;
    private BankAccount $bankAccount;
    private Provider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create();
        $this->business = Business::factory()->create(['owner_id' => $user->id]);
        $this->bankAccount = BankAccount::factory()->create([
            'business_id' => $this->business->id,
            'bank_code' => '058',
            'account_number' => '0123456789',
        ]);

        $this->provider = Provider::factory()->create([
            'identifier' => 'paystack',
            'is_active' => true,
            'is_healthy' => true,
            'is_payout_enabled' => true,
            'mode' => PaymentMode::Test,
            'supported_channels' => [PaymentChannel::BankTransfer->value],
        ]);

        // Setup Payout Fee config
        FeeConfig::factory()->create([
            'business_id' => null,
            'channel' => PaymentChannel::BankTransfer,
            'percentage' => 0,
            'fixed_amount' => 5000, // 50.00
            'is_active' => true,
        ]);

        // Fund business account
        $ledgerService = app(LedgerService::class);
        $businessAccount = $ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test);
        $fundingTx = Transaction::factory()->create([
            'business_id' => $this->business->id,
            'amount' => 1000000, // 10,000 NGN
            'reference' => 'FUND_TEST',
            'source_type' => 'funding',
            'source_id' => $this->business->id,
        ]);
        $platformClearing = $ledgerService->platformReceivable('NGN', PaymentMode::Test);
        $batch = $ledgerService->startBatch($fundingTx, 'test_funding');
        $ledgerService->post($batch, $fundingTx, $businessAccount, $platformClearing, 1000000);
    }

    public function test_successful_payout_lifecycle_with_otp(): void
    {
        // 1. Create Payout
        $createAction = app(CreatePayout::class);
        $payout = $createAction->execute($this->bankAccount, [
            'amount' => 100000, // 1000.00
            'mode' => 'test',
            'requires_otp' => true,
        ]);

        // Reload to get updated status from listener
        $payout->refresh();
        expect($payout->status)->toBe(PayoutStatus::Pending);

        // OTP should be in cache
        $otp = Cache::get("payout:otp:{$payout->id}");
        expect($otp)->not->toBeNull();

        // 2. Verify OTP
        $verifyOtpAction = app(VerifyPayoutOtp::class);
        $verifyOtpAction->execute($payout, (string) $otp);
        expect(Cache::get("payout:otp_verified:{$payout->id}"))->toBeTrue();

        // 3. Authorize Payout
        Http::fake([
            'api.paystack.co/transferrecipient' => Http::response([
                'status' => true,
                'data' => [
                    'recipient_code' => 'RCP_123',
                ],
            ]),
            'api.paystack.co/transfer' => Http::response([
                'status' => true,
                'data' => [
                    'status' => 'success',
                    'transfer_code' => 'TRF_123',
                    'reference' => 'REF_123',
                ],
            ]),
        ]);

        $authorizeAction = app(AuthorizePayout::class);
        $authorizeAction->execute($payout);

        $payout->refresh();
        expect($payout->status)->toBe(PayoutStatus::Processing);
        $this->assertDatabaseHas('authorization_attempts', [
            'source_id' => $payout->id,
            'source_type' => $payout->getMorphClass(),
            'status' => AuthorizationStatus::Success,
        ]);

        // 4. Final Processing (Simulate success)
        $processAction = app(ProcessPayout::class);
        $processAction->markSuccess($payout);
        $payout->refresh();

        expect($payout->status)->toBe(PayoutStatus::Succeeded);

        // Check Ledger Balances
        $ledgerService = app(LedgerService::class);
        $wallet = $ledgerService->businessReceivable($this->business, 'NGN', PaymentMode::Test);
        $balance = $ledgerService->getBalance($wallet);

        // Initial 1M - 100K (payout) - 5K (fee) = 895K
        // Wait, fee is deducted from the payout amount or added?
        // In this project, it seems it depends on the bearer, but usually it's deducted or business pays.
        // Let's check ResolvePayoutFee.

        expect($balance)->toBe(895000);
    }
}
