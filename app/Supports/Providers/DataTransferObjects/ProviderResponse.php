<?php

declare(strict_types=1);

namespace App\Supports\Providers\DataTransferObjects;

use App\Enums\AuthorizationStatus;

/**
 * @property-read string|null $provider_reference
 */
final class ProviderResponse
{
    public function __construct(
        public AuthorizationStatus $status,
        public ?string $providerReference = null,
        public ?BankDetailsDTO $bankDetails = null,
        public array $rawResponse = [],
        public array $metadata = []
    ) {}

    public function __get(string $name): mixed
    {
        if ($name === 'provider_reference') {
            return $this->providerReference;
        }

        return null;
    }

    public function isSuccessful(): bool
    {
        return $this->status === AuthorizationStatus::Success;
    }
}
