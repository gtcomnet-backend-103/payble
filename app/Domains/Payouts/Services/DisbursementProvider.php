<?php

declare(strict_types=1);

namespace App\Domains\Payouts\Services;

use App\Domains\Payments\Providers\Services\ProviderResolver;
use App\Domains\Payouts\Actions\SelectPayoutProvider;
use App\Domains\Payouts\DataTransferObjects\DisbursementData;
use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Models\Provider;

class DisbursementProvider
{
    /**
     * Provide a suitable provider for the given mode.
     */
    public static function provide(PaymentMode $mode): Provider
    {
        // Wrapper around SelectPayoutProvider
        return app(SelectPayoutProvider::class)->execute(Currency::NGN, $mode);
    }

    /**
     * Resolve the adapter for the given provider.
     */
    public static function adapter(Provider $provider): mixed
    {
        return app(ProviderResolver::class)->resolve($provider);
    }

    /**
     * Disburse funds using the provider.
     */
    public static function disburse(Provider $provider, DisbursementData $data): mixed
    {
        $adapter = self::adapter($provider);

        $transferData = new PayoutTransferData(
            amount: $data->amount,
            currency: 'NGN', // Defaulting as per original context implying NGN
            bank_code: $data->bankCode,
            account_number: $data->accountNumber,
            account_name: $data->accountName,
            reference: $data->reference,
            metadata: array_merge($data->metadata ?? [], ['idempotency_key' => $data->idempotencyKey])
        );

        $response = $adapter->initiateTransfer($transferData);

        if (! $response->isSuccessful()) {
            throw new \Exception('Provider returned error: ' . ($response->metadata['error'] ?? 'Unknown error'));
        }

        return $response;
    }
}
