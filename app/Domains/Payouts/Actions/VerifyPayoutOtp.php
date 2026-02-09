<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Models\Payout;
use App\Models\User;
use Illuminate\Support\Facades\App;
use RuntimeException;

final class VerifyPayoutOtp
{
    public function execute(Payout $payout, ?string $otp, ?User $user = null): void
    {
        if (! $payout->requires_otp) {
            return;
        }

        if (empty($otp)) {
            throw new RuntimeException('OTP is required for this payout.');
        }

        // TODO: Integrate with real Auth Domain when available.
        // For now, in Test/Local, accept '123456'.
        if (App::environment('local', 'testing') || $payout->mode->isTest()) {
            if ($otp !== '123456') {
                throw new RuntimeException('Invalid OTP.');
            }

            return;
        }

        // Live mode fallback
        throw new RuntimeException('OTP verification not implemented for Live mode.');
    }
}
