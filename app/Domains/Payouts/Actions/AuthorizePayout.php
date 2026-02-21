<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Models\Payout;
use App\Supports\Contracts\OtpServiceInterface;
use Exception;
use Illuminate\Validation\ValidationException;

final class AuthorizePayout
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider,
        private OtpServiceInterface $otpService,
    ) {}

    /**
     * @throws PayoutException
     */
    public function execute(Payout $payout, array $data = []): Payout
    {
        $otp = $data['otp'] ?? null;
        if ($payout->requires_otp && ! $otp) {
            throw ValidationException::withMessages([
                'otp' => 'The OTP is required.',
            ]);
        }

        if (! $payout->status->is(PayoutStatus::Pending)) {
            throw new PayoutException('this payout cannot be authorized in this state');
        }

        if ($payout->mode->isTest() && ! app()->environment('testing')) {
            throw new PayoutException('this payout cannot be authorized in test mode');
        }

        if ($payout->requires_otp && ! $this->otpService->verify($payout->originator, $otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The OTP provided is invalid.',
            ]);
        }

        $bankAccount = $payout->bankAccount;
        $provider = $this->disbursementProvider->provider($payout->mode);

        try {
            $response = $this->disbursementProvider->transfer(
                provider: $provider,
                reference: $payout->provider_reference,
                accountNumber: $bankAccount->account_number,
                bankCode: $bankAccount->bank_code,
                amount: $payout->net_amount,
            );

            $payout->update([
                'provider_id' => $provider->id,
                'status' => PayoutStatus::Processing,
            ]);

            if ($response->status->isFinal()) {
                app(ProcessPayout::class)->execute($payout);
            }
        } catch (Exception $e) {
            $payout->update([
                'status' => PayoutStatus::Failed,
                'metadata' => array_merge($payout->metadata ?? [], ['failure_reason' => $e->getMessage()]),
            ]);

            throw $e;
        }

        return $payout;
    }
}
