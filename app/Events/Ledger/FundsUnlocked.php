<?php

declare(strict_types=1);

namespace App\Events\Ledger;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class FundsUnlocked
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public readonly int $payoutId,
        public readonly int $amountUnlocked,
        public readonly string $idempotencyKey,
    ) {}
}
