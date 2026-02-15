<?php

declare(strict_types=1);

namespace App\Domains\Payouts\DataTransferObjects;

use App\Models\Provider;

final class PayoutTransferData
{
    public function __construct(
        public int $amount,
        public string $currency,
        public string $bank_code,
        public string $account_number,
        public string $account_name,
        public string $reference,
        public array $metadata = [],
        public string $status = 'pending',
        public ?string $provider_reference = null,
    ) {}
}
