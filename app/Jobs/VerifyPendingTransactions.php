<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Domains\Payments\Actions\ProcessPaymentAttempt;
use App\Enums\PaymentStatus;
use App\Models\AuthorizationAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

final class VerifyPendingTransactions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(ProcessPaymentAttempt $processPaymentAttempt): void
    {
        // Find attempts that are pending, havent completed, and are older than 5 minutes
        $attempts = AuthorizationAttempt::query()
            ->whereNull('completed_at')
            ->where('updated_at', '<', now()->subMinutes(5))
            ->whereHas('paymentIntent', function ($query) {
                $query->where('status', '!=', PaymentStatus::Success);
            })
            ->cursor();

        Log::info('attempts', $attempts->toArray());

        foreach ($attempts as $attempt) {
            $processPaymentAttempt->execute($attempt);
        }
    }
}
