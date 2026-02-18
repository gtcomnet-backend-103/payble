<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Domains\Payouts\Contracts\LedgerPostingServiceInterface;
use App\Domains\Payouts\Contracts\LedgerServiceInterface;
use App\Supports\Contracts\OtpServiceInterface;
use App\Supports\Providers\Services\DisbursementProvider;
use App\Supports\Services\LedgerPayoutPostingService;
use App\Supports\Services\LedgerService;
use App\Supports\Services\OtpService;
use App\Supports\Services\FeeCalculator;
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
        $this->app->bind(BankAccountResolver::class, DisbursementProvider::class);
        $this->app->bind(FeeCalculatorInterface::class, FeeCalculator::class);
        $this->app->bind(LedgerPostingServiceInterface::class, LedgerPayoutPostingService::class);
        $this->app->bind(LedgerServiceInterface::class, LedgerService::class);
    }
}
