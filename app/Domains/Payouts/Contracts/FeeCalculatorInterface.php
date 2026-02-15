<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Contracts;

use App\Enums\Currency;

interface FeeCalculatorInterface
{
    /**
     * Calculate the fee for a payout amount.
     * The fee is always paid by the business and deducted from the payout amount.
     */
    public function calculate(int $amount, Currency $currency): int;
}
