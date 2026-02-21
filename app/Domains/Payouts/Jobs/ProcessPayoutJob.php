<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Jobs;

use App\Domains\Payouts\Actions\ProcessPayout;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProcessPayoutJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 7;

    public function __construct(
        public Payout $payout
    ) {}

    public function backoff(): array
    {
        return [
            300,    // 5 mins
            900,    // 15 mins
            1800,   // 30 mins
            3600,   // 1 hour
            10800,  // 3 hours
            21600,  // 6 hours
        ];
    }

    public function handle(ProcessPayout $processPayout): void
    {
        $processPayout->execute($this->payout);

        if ($this->payout->status->is(PayoutStatus::Processing)) {
            $this->payout->touch();

            if ($this->attempts() >= $this->tries) {
                $this->payout->update(['status' => PayoutStatus::ReconciliationRequired]);

                return;
            }

            $this->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->payout->status->is(PayoutStatus::Processing)) {
            $this->payout->update(['status' => PayoutStatus::Unknown]);
        }

        Log::error("ProcessPayoutJob failed for payout {$this->payout->id}: ".$exception->getMessage());
    }
}
