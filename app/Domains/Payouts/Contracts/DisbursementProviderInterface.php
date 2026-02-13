<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Models\Provider;

interface DisbursementProviderInterface
{
    /**
     * @param string $reference
     * @param string $accountNumber
     * @param string $bankCode
     * @return array{status: string}
     */
    public function transfer(Provider $provider, string $reference, string $accountNumber, string $bankCode): array;

    public function provider(): Provider;

    public function verify(Provider $provider, string $reference): PayoutTransferData;
}
