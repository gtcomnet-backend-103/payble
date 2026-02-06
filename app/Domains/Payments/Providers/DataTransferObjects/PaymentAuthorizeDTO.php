<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\DataTransferObjects;

use App\Enums\Currency;
use App\Enums\PaymentChannel;

final class PaymentAuthorizeDTO
{
    public function __construct(
        public string $reference,
        public int $amount,
        public Currency $currency,
        public PaymentChannel $channel,
        public CustomerDTO $customer,
        public array $metadata = [],
        public array $channelDetails = []
    ) {}
}
