<?php

declare(strict_types=1);

namespace App\Supports\Providers\Services;

use App\Domains\Payouts\Contracts\BankAccountResolver;
use App\Domains\Payouts\Contracts\DisbursementProviderInterface;
use App\Domains\Payouts\DataTransferObjects\BankAccountDetails;
use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\PaymentMode;
use App\Models\Provider;
use App\Supports\Providers\Adapters\PaystackAdapter;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class DisbursementProvider implements BankAccountResolver, DisbursementProviderInterface
{
    public function __construct(private ProviderResolver $providerResolver) {}

    public function provider(?PaymentMode $mode = null): Provider
    {
        $mode = $mode ?? PaymentMode::Test;

        try {
            return Provider::query()
                ->where('is_payout_enabled', true)
                ->where('is_active', true)
                ->where('mode', $mode)
                ->firstOrFail();
        } catch (ModelNotFoundException) {
            throw new ModelNotFoundException('Provider not found');
        }
    }

    public function transfer(Provider $provider, string $reference, string $accountNumber, string $bankCode, int $amount): ProviderResponse
    {
        $adapter = $this->providerResolver->resolve($provider);

        $transferData = new PayoutTransferData(
            amount: $amount,
            currency: 'NGN',
            bank_code: $bankCode,
            account_number: $accountNumber,
            account_name: '',
            reference: $reference,
            metadata: []
        );

        return $adapter->initiateTransfer($transferData);
    }

    public function verify(Provider $provider, string $reference): ProviderResponse
    {
        $adapter = app(ProviderResolver::class)->resolve($provider);

        return $adapter->verifyTransfer($reference);
    }

    public function resolveAccount(string $bankCode, string $accountNumber): BankAccountDetails
    {
        return app(PaystackAdapter::class)->validateAccount($accountNumber, $bankCode);
    }
}
