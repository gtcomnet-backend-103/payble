<?php

declare(strict_types=1);

namespace App\Domains\Payments\Actions;

use App\Enums\PaymentChannel;
use App\Models\Provider;

final class SelectProvider
{
    /**
     * Select the best provider for the given channel.
     *
     * @param PaymentChannel $channel
     * @return Provider
     * @throws \Exception
     */
    public function execute(PaymentChannel $channel): Provider
    {
        $providers = Provider::query()
            ->where('is_active', true)
            ->where('is_healthy', true)
            ->whereJsonContains('supported_channels', $channel->value)
            ->get();

        if ($providers->isEmpty()) {
            throw new \Exception("No healthy providers available for channel: {$channel->value}");
        }

        // For now, we pick the one with the lowest "fee" in metadata,
        // or just the first one if not specified.
        return $providers->sortBy(function (Provider $provider) {
            return $provider->metadata['fee_percentage'] ?? 999;
        })->first();
    }
}
