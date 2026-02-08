<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domains\Ledger\Services\LedgerService;
use App\Models\Provider;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

final class WalletBalanceOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        $ledgerService = app(LedgerService::class);
        $platformBalance = $ledgerService->platformRevenue('NGN');
        $platformClearing = $ledgerService->platformReceivable('NGN');
        $providerFeeExpenseBalance = $ledgerService->providerFee('NGN');

        return [
            Stat::make('Platform Revenue', Number::currency($ledgerService->getBalance($platformBalance) / 100, $platformBalance->currency)),
            Stat::make('Platform Clearing', Number::currency($ledgerService->getBalance($platformClearing) / 100, $platformClearing->currency)),
            Stat::make('Fee Expense', Number::currency($ledgerService->getBalance($providerFeeExpenseBalance) / 100, $providerFeeExpenseBalance->currency)),
            ...Provider::all()->map(function (Provider $provider) use ($ledgerService) {
                $providerClearing = $ledgerService->providerReceivable($provider, 'NGN');

                return Stat::make("$provider->name Clearing", Number::currency($ledgerService->getBalance($providerClearing) / 100, $providerClearing->currency));
            }),
        ];
    }
}
