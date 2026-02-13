<?php

declare(strict_types=1);

namespace Infrastructure\Payment\Adapters;

use App\Domains\Payments\Providers\DataTransferObjects\CustomerDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Models\Provider;
use Domain\Payment\Contracts\PaymentGatewayInterface;
use Domain\Payment\Enums\Channel;

final readonly class PaymentGatewayAdapter implements PaymentGatewayInterface
{
    public function authorize(string $providerId, array $data): array
    {
        $provider = Provider::query()->findOrFail($providerId);

        $dto = new PaymentAuthorizeDTO(
            reference: $data['reference'],
            amount: $data['amount'],
            currency: \App\Enums\Currency::from($data['currency']),
            channel: \App\Enums\PaymentChannel::from($data['channel']->value),
            customer: new CustomerDTO(
                firstName: $data['customer']['firstName'],
                lastName: $data['customer']['lastName'],
                email: $data['customer']['email'],
                phone: $data['customer']['phone']
            ),
            metadata: $data['metadata'],
            channelDetails: match ($data['channel']) {
                Channel::Card => $data['card'] ?? [],
                default => []
            }
        );

        $response = PaymentProvider::authorize($provider, $dto);

        return [
            'provider_reference' => $response->providerReference,
            'status' => $response->status->value,
            'action' => match ($response->status) {
                \App\Enums\AuthorizationStatus::PendingPhone => 'phone',
                \App\Enums\AuthorizationStatus::PendingPin => 'pin',
                \App\Enums\AuthorizationStatus::PendingOtp => 'otp',
                \App\Enums\AuthorizationStatus::PendingTransfer => 'transfer',
                default => null,
            },
            'raw_response' => array_merge($response->rawResponse, [
                'bank_details' => $response->bankDetails?->toArray(),
            ]),
            'metadata' => $response->metadata,
        ];
    }

    public function getProviderFee(string $providerId, Channel $channel, int $amount): int
    {
        $provider = Provider::query()->findOrFail($providerId);

        return PaymentProvider::getFee(
            $provider,
            \App\Enums\PaymentChannel::from($channel->value),
            $amount
        );
    }
}
