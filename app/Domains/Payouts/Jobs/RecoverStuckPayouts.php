<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Jobs;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

final class RecoverStuckPayouts implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $stuckThreshold = now()->subMinutes(5);

        Payout::query()
            ->where('status', PayoutStatus::Processing)
            ->where('updated_at', '<', $stuckThreshold)
            ->chunkById(100, function ($payouts) {
                foreach ($payouts as $payout) {
                    Log::info("Recovering stuck payout: {$payout->id}", [
                        'reference' => $payout->reference,
                        'last_update' => $payout->updated_at->toDateTimeString(),
                    ]);

                    ProcessPayoutJob::dispatch($payout);
                }
            });
    }
}
