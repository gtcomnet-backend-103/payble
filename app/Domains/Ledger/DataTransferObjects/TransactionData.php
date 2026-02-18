<?php

declare(strict_types=1);

namespace App\Domains\Ledger\DataTransferObjects;

use App\Enums\Currency;
use App\Enums\FeeChannel;
use App\Enums\PaymentMode;

final readonly class TransactionData
{

    public function __construct(
        public int $businessId,
        public string $reference,
        public int $amount,
        public int $fee,
        public Currency $currency,
        public PaymentMode $mode,
        public ?array $metadata = null,
    ) {}
}
