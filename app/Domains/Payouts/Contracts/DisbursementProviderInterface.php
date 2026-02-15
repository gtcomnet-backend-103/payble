<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Models\Provider;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;

interface DisbursementProviderInterface
{
    /**
     * @param Provider $provider
     * @param string $reference
     * @param string $accountNumber
     * @param string $bankCode
     * @param int $amount
     * @return array{status: string}
     */
    public function transfer(Provider $provider, string $reference, string $accountNumber, string $bankCode, int $amount): ProviderResponse;

    /**
     * Get provider from db where can_payout is true
     * @return Provider
     */
    public function provider(): Provider;

    public function verify(Provider $provider, string $reference): ProviderResponse;
}
