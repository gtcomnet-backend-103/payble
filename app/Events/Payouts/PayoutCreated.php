<?php

declare(strict_types=1);

namespace App\Events\Payouts;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final readonly class PayoutCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $payoutId,
        public string $idempotencyKey,
    ) {}
}
