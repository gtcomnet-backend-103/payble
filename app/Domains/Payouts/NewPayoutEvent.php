<?php

namespace App\Domains\Payouts;

use App\Models\Payout;
use Illuminate\Foundation\Events\Dispatchable;

class NewPayoutEvent
{
    use Dispatchable;

    public function __construct(public Payout $payout)
    {
    }
}
