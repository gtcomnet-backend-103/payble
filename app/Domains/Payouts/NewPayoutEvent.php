<?php

declare(strict_types=1);

namespace App\Domains\Payouts;

use App\Models\Payout;
use Illuminate\Foundation\Events\Dispatchable;

final class NewPayoutEvent
{
    use Dispatchable;

    public function __construct(public Payout $payout) {}
}
