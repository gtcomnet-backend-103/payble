<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Jobs;

use App\Domains\Payouts\Actions\ProcessPayout;
use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ProcessPayoutJob implements ShouldQueue
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

        if ($this->payout->status->is(\App\Enums\PayoutStatus::Processing)) {
            $this->payout->touch();

            if ($this->attempts() >= $this->tries) {
                $this->payout->update(['status' => \App\Enums\PayoutStatus::ReconciliationRequired]);

                return;
            }

            $this->release();
        }
    }

    public function failed(Throwable $exception): void
    {
        if ($this->payout->status->is(\App\Enums\PayoutStatus::Processing)) {
            $this->payout->update(['status' => \App\Enums\PayoutStatus::Unknown]);
        }

        \Illuminate\Support\Facades\Log::error("ProcessPayoutJob failed for payout {$this->payout->id}: " . $exception->getMessage());
    }
}
