<?php

declare(strict_types=1);

namespace App\Domains\Payouts\DataTransferObjects;

final class BankAccountDetails
{
    public function __construct(
        public readonly string $accountName,
        public readonly string $accountNumber,
        public string $bankCode,
    ) {}
}
