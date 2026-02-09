<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\DataTransferObjects;

use App\Enums\AuthorizationStatus;

final class ProviderResponse
{
    public function __construct(
        public AuthorizationStatus $status,
        public ?string $providerReference = null,
        public ?BankDetailsDTO $bankDetails = null,
        public array $rawResponse = [],
        public array $metadata = []
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status === AuthorizationStatus::Success;
    }
}
