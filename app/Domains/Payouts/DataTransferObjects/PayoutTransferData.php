<?php

declare(strict_types=1);

namespace App\Domains\Payouts\DataTransferObjects;

final class PayoutTransferData
{
    public function __construct(
        public bool $status,
        public array $payload = [],
    ) {}
}
