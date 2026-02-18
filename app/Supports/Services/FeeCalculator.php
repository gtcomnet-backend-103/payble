<?php

declare(strict_types=1);

namespace App\Supports\Services;

use App\Domains\Payouts\Contracts\FeeCalculatorInterface;
use App\Enums\Currency;
use App\Enums\FeeChannel;
use App\Enums\PaymentChannel;
use App\Models\FeeConfig;
use Illuminate\Support\Facades\Context;

final class FeeCalculator implements FeeCalculatorInterface
{
    /**
     * Calculate the fee for a payout amount.
     * Payouts currently use the BankTransfer channel by default.
     */
    public function calculate(int $amount, Currency $currency, FeeChannel $channel): int
    {
        $businessId = Context::get('business_id');

        // 1. Check for business-specific fee for Bank Transfer
        $config = FeeConfig::query()
            ->where('business_id', $businessId)
            ->where('channel', $channel)
            ->where('is_active', true)
            ->first();

        // 2. Fallback to global fee
        if (! $config) {
            $config = FeeConfig::query()
                ->whereNull('business_id')
                ->where('channel', $channel)
                ->where('is_active', true)
                ->first();
        }

        if (! $config) {
            return 0;
        }

        // Calculate fee: (amount * percentage / 100) + fixed_amount
        $calculatedFee = (int) (($amount * $config->percentage) / 100) + $config->fixed_amount;

        // Apply min/max constraints
        if ($calculatedFee < $config->min_fee) {
            $calculatedFee = $config->min_fee;
        }

        if ($config->max_fee && $calculatedFee > $config->max_fee) {
            $calculatedFee = $config->max_fee;
        }

        return $calculatedFee;
    }
}
