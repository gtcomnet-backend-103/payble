<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Payments\Events\TransactionSuccessful::class,
            [\App\Listeners\ProcessTransactionLedger::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domains\Payments\Events\TransactionSuccessful::class,
            [\App\Listeners\SendPaymentNotifications::class, 'handle']
        );
    }
}
