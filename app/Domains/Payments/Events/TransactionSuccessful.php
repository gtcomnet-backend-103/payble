<?php

declare(strict_types=1);

namespace App\Domains\Payments\Events;

use App\Enums\FeeBearer;
use App\Models\Provider;
use App\Models\Transaction;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class TransactionSuccessful
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Transaction $transaction,
        public Provider $provider,
        public FeeBearer $bearer,
        public int $totalFee,
        public int $providerFee,
    ) {}
}
