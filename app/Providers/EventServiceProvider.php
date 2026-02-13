<?php

declare(strict_types=1);

namespace App\Providers;

use App\Events\Payouts\PayoutCreated;
use App\Events\Payouts\PayoutFailed;
use App\Events\Payouts\PayoutSucceeded;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Payout creation triggers fund reservation and OTP
        PayoutCreated::class => [
            \App\Listeners\Payouts\ReservePayoutFunds::class,
        ],

        // Payout Success/Failure handled via ledger finalization/reversal
        PayoutSucceeded::class => [
            \App\Listeners\Payouts\PostPayoutFunds::class,
        ],

        PayoutFailed::class => [
            \App\Listeners\Payouts\ReversePayoutFunds::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
