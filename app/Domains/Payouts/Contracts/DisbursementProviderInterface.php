<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Enums\PaymentMode;
use App\Models\Provider;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;

interface DisbursementProviderInterface
{
    public function transfer(Provider $provider, string $reference, string $accountNumber, string $bankCode, int $amount): ProviderResponse;

    /**
     * Get provider from db where can_payout is true
     */
    public function provider(?PaymentMode $mode = null): Provider;

    public function verify(Provider $provider, string $reference): ProviderResponse;

    public function listBanks(Provider $provider): array;
}
