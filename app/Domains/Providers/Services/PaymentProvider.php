<?php

declare(strict_types=1);

namespace App\Domains\Providers\Services;

use App\Domains\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Providers\Testing\FakePaymentProvider;
use App\Domains\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Models\Provider;

class PaymentProvider
{
    public function __construct(protected ProviderResolver $resolver) {}

    public static function fake(): FakePaymentProvider
    {
        \App\Domains\Providers\Facades\PaymentProvider::swap($fake = new FakePaymentProvider());

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
