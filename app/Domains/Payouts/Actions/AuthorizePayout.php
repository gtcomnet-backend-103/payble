<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Models\Payout;
use App\Supports\Contracts\OtpServiceInterface;
use Illuminate\Support\Str;
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
            ValidationException::withMessages([
                'otp' => 'The OTP is required.',
            ]);
        }

        if (! $payout->status->is(PayoutStatus::Pending)) {
            throw new PayoutException('this payout cannot be authorized in this state');
        }

        if ($payout->mode->isTest()) {
            throw new PayoutException('this payout cannot be authorized in test mode');
        }

        if ($payout->requires_otp && ! $this->otpService->verify($payout, $otp)) {
            throw ValidationException::withMessages([
                'otp' => 'The OTP provided is invalid.',
            ]);
        }

        $reference = Str::uuid()->toString();
        $bankAccount = $payout->bankAccount;

        $provider = $this->disbursementProvider->provider();
        $this->disbursementProvider->transfer(
            provider: $provider,
            reference: $reference,
            accountNumber: $bankAccount->account_number,
            bankCode: $bankAccount->bank_code
        );

        $payout->update([
            'provider_id' => $provider->id,
            'provider_reference' => $reference,
            'status' => PayoutStatus::Processing,
        ]);

        return $payout;
    }
}
