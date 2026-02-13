<?php

declare(strict_types=1);

namespace App\Events\Payouts;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PayoutAuthorized
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $payoutId,
        public string $providerReference,
        public string $idempotencyKey
    ) {}
}
