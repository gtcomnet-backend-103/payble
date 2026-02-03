<?php

declare(strict_types=1);

namespace App\Domains\Providers\DataTransferObjects;

use App\Enums\Currency;
use App\Enums\PaymentChannel;
use InvalidArgumentException;

final class CustomerDTO
{
    public function __construct(
        public ?string $firstName = null,
        public ?string $lastName = null,
        public ?string $email = null,
        public ?string $phone = null,
        public array $metadata = []
    ) {
        if (!$email && !$phone) {
            throw new InvalidArgumentException('Email or Phone is required.');
        }
    }
}
