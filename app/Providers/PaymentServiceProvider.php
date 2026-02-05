<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Payments\Providers\Services\PaymentProvider;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('payment-provider', function ($app) {
            return $app->make(PaymentProvider::class);
        });
    }

    public function boot(): void
    {
        //
    }
}
