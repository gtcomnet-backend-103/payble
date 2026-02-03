<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Enums\PaymentChannel;
use App\Models\FeeConfig;
use App\Models\PaymentIntent;

final class ResolvePaymentFee
{
    /**
     * Resolve the fee for a payment attempt.
     *
     * @param PaymentIntent $paymentIntent
     * @param PaymentChannel $channel
     * @return int
     */
    public function execute(PaymentIntent $paymentIntent, PaymentChannel $channel): int
    {
        // 1. Check for business-specific fee
        $config = FeeConfig::query()
            ->where('business_id', $paymentIntent->business_id)
            ->where('channel', $channel->value)
            ->where('is_active', true)
            ->first();

        // 2. Fallback to global fee
        if (! $config) {
            $config = FeeConfig::query()
                ->whereNull('business_id')
                ->where('channel', $channel->value)
                ->where('is_active', true)
                ->first();
        }

        if (! $config) {
            return 0; // Or a default global fallback if required
        }

        // Calculate fee: (amount * percentage / 100) + fixed_amount
        $calculatedFee = (int) (($paymentIntent->amount * $config->percentage) / 100) + $config->fixed_amount;

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
