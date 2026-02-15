<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Supports\Contracts\OtpServiceInterface;
use App\Supports\Providers\Services\DisbursementProvider;
use App\Supports\Services\LedgerPayoutService;
use App\Supports\Services\OtpService;
use App\Supports\Services\PayoutFeeCalculator;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->app->bind(OtpServiceInterface::class, OtpService::class);
        $this->app->bind(DisbursementProviderInterface::class, DisbursementProvider::class);
        $this->app->bind(FeeCalculatorInterface::class, PayoutFeeCalculator::class);
        $this->app->bind(LedgerServiceInterface::class, LedgerPayoutService::class);
    }
}
