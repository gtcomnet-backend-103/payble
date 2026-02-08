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
            \App\Events\TransactionSuccessful::class,
            [\App\Domains\Ledger\Listeners\ProcessTransactionLedger::class, 'handle']
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Events\TransactionSuccessful::class,
            [\App\Domains\Payments\Listeners\SendPaymentNotifications::class, 'handle']
        );
    }
}
