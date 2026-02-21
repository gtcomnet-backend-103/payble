<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::job(new App\Jobs\VerifyPendingTransactions)->everyMinute();
Schedule::job(new App\Domains\Payouts\Jobs\RecoverStuckPayouts)->everyMinute();
Schedule::command('payouts:reconcile')->dailyAt('00:00');
