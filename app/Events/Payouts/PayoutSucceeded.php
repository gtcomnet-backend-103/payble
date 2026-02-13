<?php

declare(strict_types=1);

namespace App\Events\Payouts;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class PayoutSucceeded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $payoutId,
        public readonly string $providerReference,
        public readonly string $idempotencyKey,
    ) {}
}
