<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Services;

use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Domains\Payments\Providers\Testing\FakePaymentProvider;
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
        return $this->resolver->resolve($provider)->authorize($dto);
    }

    public function verifyWebhook(Provider $provider, array $payload, array $headers): bool
    {
        return $this->resolver->resolve($provider)->verifyWebhook($payload, $headers);
    }

    public function normalizeWebhook(Provider $provider, array $payload): WebhookPayloadDTO
    {
        return $this->resolver->resolve($provider)->normalizeWebhook($payload);
    }

    public function verifyTransaction(Provider $provider, string $reference): ProviderResponse
    {
        return $this->resolver->resolve($provider)->verifyTransaction($reference);
    }

    public function getFee(Provider $provider, \App\Enums\PaymentChannel $channel, int $amount): int
    {
        return $this->resolver->resolve($provider)->getFee($channel, $amount);
    }
}
