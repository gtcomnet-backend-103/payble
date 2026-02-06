<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Services;

use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Domains\Payments\Providers\Testing\FakePaymentProvider;
use App\Enums\PaymentChannel;
use App\Models\Provider;

class PaymentProvider
{
    public function __construct(private ProviderResolver $resolver) {}

    public static function fake(): FakePaymentProvider
    {
        \App\Domains\Payments\Providers\Facades\PaymentProvider::swap($fake = new FakePaymentProvider());

        return $fake;
    }

    public function authorize(Provider $provider, PaymentAuthorizeDTO $dto): ProviderResponse
    {
        return $this->resolveAdapter($provider)->authorize($dto);
    }

    public function verifyWebhook(Provider $provider, array $payload, array $headers): bool
    {
        return $this->resolveAdapter($provider)->verifyWebhook($payload, $headers);
    }

    public function normalizeWebhook(Provider $provider, array $payload): WebhookPayloadDTO
    {
        return $this->resolveAdapter($provider)->normalizeWebhook($payload);
    }

    public function verifyTransaction(Provider $provider, string $reference): ProviderResponse
    {
        return $this->resolveAdapter($provider)->verifyTransaction($reference);
    }

    public function validate(Provider $provider, string $providerReference, \App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO $dto): ProviderResponse
    {
        return $this->resolveAdapter($provider)->validate($providerReference, $dto);
    }

    public function getFee(Provider $provider, PaymentChannel $channel, int $amount): int
    {
        return $this->resolveAdapter($provider)->getFee($channel, $amount);
    }

    private function resolveAdapter(Provider $provider): ProviderAdapter
    {
        return $this->resolver->resolve($provider);
    }
}
