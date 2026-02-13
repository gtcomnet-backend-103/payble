<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Enums\PayoutStatus;
use App\Exceptions\PayoutException;
use App\Models\Payout;
use Illuminate\Support\Str;

final class AuthorizePayout
{
    public function __construct(private DisbursementProviderInterface $disbursementProvider) {}

    /**
     * @throws PayoutException
     */
    public function execute(Payout $payout): Payout
    {
        if (! $payout->status->is(PayoutStatus::Pending)) {
            throw new PayoutException('this payout cannot be authorized in this state');
        }

        if ($payout->mode->isTest()) {
            throw new PayoutException('this payout cannot be authorized in test mode');
        }

        $reference = Str::uuid()->toString();
        $bankAccount = $payout->bankAccount;

        $provider = $this->disbursementProvider->provider();
        $this->disbursementProvider->transfer($provider, $reference, $bankAccount->account_number, $bankAccount->bank_code);

        $payout->update([
            'provider_id' => $provider->id,
            'provider_reference' => $reference,
            'status' => PayoutStatus::Processing,
        ]);

        return $payout;
    }
}
