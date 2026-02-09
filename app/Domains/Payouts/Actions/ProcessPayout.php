<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Ledger\Actions\RecordPayoutLedgerPostings;
use App\Domains\Ledger\Actions\ReversePayoutLedgerPostings;
use App\Domains\Payments\Providers\Services\ProviderResolver;
use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\PayoutStatus;
use App\Enums\TransactionStatus;
use App\Models\Payout;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ProcessPayout
{
    public function __construct(
        private readonly VerifyPayoutOtp $verifyPayoutOtp,
        private readonly SelectPayoutProvider $selectPayoutProvider,
        private readonly RecordPayoutLedgerPostings $recordLedgerPostings,
        private readonly ReversePayoutLedgerPostings $reverseLedgerPostings,
        private readonly ProviderResolver $providerResolver
    ) {}

    public function execute(Payout $payout, ?string $otp = null, ?User $user = null): Payout
    {
        if ($payout->status !== PayoutStatus::Pending) {
            return $payout;
        }

        // 1. Verify OTP
        $this->verifyPayoutOtp->execute($payout, $otp, $user);

        // 2. Select Provider
        $provider = $this->selectPayoutProvider->execute($payout->currency->value, $payout->mode);

        // 3. Reserve Funds & Update State
        DB::transaction(function () use ($payout, $provider) {
            $payout->update([
                'provider_id' => $provider->id,
                'status' => PayoutStatus::Processing,
            ]);

            $payout->transaction->update([
                'status' => TransactionStatus::Processing,
            ]);

            $this->recordLedgerPostings->execute($payout->transaction);
        });

        // 4. Call Provider
        try {
            $adapter = $this->providerResolver->resolve($provider);

            $bankDetails = $payout->metadata['bank_details'] ?? [];

            $transferData = new PayoutTransferData(
                amount: $payout->amount,
                currency: $payout->currency->value,
                bank_code: $bankDetails['bank_code'] ?? '',
                account_number: $bankDetails['account_number'] ?? '',
                account_name: $bankDetails['account_name'] ?? '',
                reference: $payout->reference,
                metadata: $payout->metadata ?? []
            );

            $response = $adapter->initiateTransfer($transferData);

            if ($response->isSuccessful()) {
                // Update to Completed (or Processing if pending)
                // For now assume Success = Completed for simplicity unless response says pending?
                $finalStatus = PayoutStatus::Completed;

                DB::transaction(function () use ($payout, $finalStatus) {
                    $payout->update(['status' => $finalStatus]);
                    $payout->transaction->update([
                        'status' => $finalStatus === PayoutStatus::Completed ? TransactionStatus::Success : TransactionStatus::Failed,
                    ]);
                });
            } else {
                throw new Exception('Provider returned error: '.($response->metadata['error'] ?? 'Unknown error'));
            }
        } catch (Throwable $e) {
            // Reverse
            DB::transaction(function () use ($payout, $e) {
                $this->reverseLedgerPostings->execute($payout->transaction);

                $metadata = $payout->metadata ?? [];
                $metadata['error'] = $e->getMessage();

                $payout->update(['status' => PayoutStatus::Failed, 'metadata' => $metadata]);
                $payout->transaction->update(['status' => TransactionStatus::Failed]);
            });

            throw $e;
        }

        return $payout->refresh();
    }
}
