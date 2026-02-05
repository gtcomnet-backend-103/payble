<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\DataTransferObjects;

final class BankDetailsDTO
{
    public function __construct(
        public string $accountNumber,
        public string $bankName,
        public string $accountName,
        public ?string $expiresAt = null,
        public array $metadata = []
    ) {}

    public function toArray(): array
    {
        return [
            'account_number' => $this->accountNumber,
            'bank_name' => $this->bankName,
            'account_name' => $this->accountName,
            'expires_at' => $this->expiresAt,
            ...$this->metadata,
        ];
    }
}
