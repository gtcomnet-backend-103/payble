<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Payments\Events\TransactionSuccessful;
use App\Domains\Payouts\NewPayoutEvent;
use App\Listeners\ProcessTransactionLedger;
use App\Listeners\SendOneTimePasswordListener;
use App\Listeners\SendPaymentNotifications;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

final class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        NewPayoutEvent::class => [
            SendOneTimePasswordListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Event::listen(
            TransactionSuccessful::class,
            [ProcessTransactionLedger::class, 'handle']
        );

        Event::listen(
            TransactionSuccessful::class,
            [SendPaymentNotifications::class, 'handle']
        );
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
