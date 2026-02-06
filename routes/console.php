<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

Schedule::job(new App\Jobs\VerifyPendingTransactions)->everyMinute();
