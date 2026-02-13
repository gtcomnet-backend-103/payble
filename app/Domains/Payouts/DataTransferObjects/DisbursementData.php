<?php

declare(strict_types=1);

namespace App\Domains\Payouts\DataTransferObjects;

final readonly class DisbursementData
{
    public function __construct(
        public string $reference,
        public int $amount,
        public string $bankCode,
        public string $accountNumber,
        public string $accountName,
        public string $idempotencyKey,
        public array $metadata = []
    ) {}
}
