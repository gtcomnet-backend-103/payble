<?php

declare(strict_types=1);

use App\Domains\Payouts\Jobs\ProcessPayoutJob;
use App\Domains\Payouts\Jobs\RecoverStuckPayouts;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use Illuminate\Support\Facades\Queue;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

it('recovers payouts stuck in processing for more than 5 minutes', function () {
    Queue::fake();

    // 1. A payout stuck for 10 minutes
    $stuckPayout = Payout::factory()->create([
        'status' => PayoutStatus::Processing,
        'updated_at' => now()->subMinutes(10),
    ]);

    // Force the updated_at in database because factory/create might touch it
    Illuminate\Support\Facades\DB::table('payouts')
        ->where('id', $stuckPayout->id)
        ->update(['updated_at' => now()->subMinutes(10)]);

    // 2. A payout that just started processing (1 minute ago)
    $recentPayout = Payout::factory()->create([
        'status' => PayoutStatus::Processing,
        'updated_at' => now()->subMinute(),
    ]);

    // 3. A payout that is NOT in processing status (e.g. Pending) but old
    $oldPendingPayout = Payout::factory()->create([
        'status' => PayoutStatus::Pending,
        'updated_at' => now()->subMinutes(10),
    ]);

    (new RecoverStuckPayouts())->handle();

    // Assert ProcessPayoutJob was dispatched for the stuck one
    Queue::assertPushed(ProcessPayoutJob::class, function ($job) use ($stuckPayout) {
        return $job->payout->id === $stuckPayout->id;
    });

    // Assert ProcessPayoutJob was NOT dispatched for the recent one
    Queue::assertNotPushed(ProcessPayoutJob::class, function ($job) use ($recentPayout) {
        return $job->payout->id === $recentPayout->id;
    });

    // Assert ProcessPayoutJob was NOT dispatched for the old pending one
    Queue::assertNotPushed(ProcessPayoutJob::class, function ($job) use ($oldPendingPayout) {
        return $job->payout->id === $oldPendingPayout->id;
    });
});
