<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Actions;

use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Models\Provider;
use RuntimeException;

final class SelectPayoutProvider
{
    public function execute(string $currency, PaymentMode $mode, PaymentChannel $channel = PaymentChannel::BankTransfer): Provider
    {
        /** @var Provider|null $provider */
        $provider = Provider::forPayout($mode)
            ->whereJsonContains('supported_channels', $channel->value)
            ->first();

        if (! $provider) {
            throw new RuntimeException("No active payout provider found for channel {$channel->value} in {$mode->value} mode.");
        }

        return $provider;
    }
}
