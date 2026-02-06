<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\DataTransferObjects;

final class PaymentValidateDTO
{
    public function __construct(
        public ?string $pin = null,
        public ?string $otp = null,
        public ?string $phone = null,
        public ?string $birthday = null,
        public ?string $address = null,
        public array $customData = [],
    ) {}

    public function toArray(): array
    {
        return array_filter([
            'pin' => $this->pin,
            'otp' => $this->otp,
            'phone' => $this->phone,
            'birthday' => $this->birthday,
            'address' => $this->address,
            ...$this->customData,
        ], fn ($value) => ! is_null($value));
    }
}
