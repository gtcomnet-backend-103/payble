<?php

namespace App\Domains\Payments\Interfaces;

use App\Enums\PaymentChannel;

interface FeeCalculatorInterface
{
    public function calculateForChannel(int $amount, PaymentChannel $channel): int;
}
