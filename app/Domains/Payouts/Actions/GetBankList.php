<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Enums\PaymentMode;
use App\Models\Business;

final readonly class GetBankList
{
    public function __construct(
        private DisbursementProviderInterface $disbursementProvider
    ) {}

    public function execute(Business $business): array
    {
        $mode = PaymentMode::tryFrom(config('app.payment_mode') ?? PaymentMode::Live->value);
        $provider = $this->disbursementProvider->provider($mode);

        return $this->disbursementProvider->listBanks($provider);
    }
}
