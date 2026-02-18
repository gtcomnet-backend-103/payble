<?php

declare(strict_types=1);

namespace App\Supports\Providers\Services;

use App\Enums\FeeChannel;
use App\Enums\PaymentChannel;
use App\Models\Provider;
use App\Supports\Providers\Contracts\ProviderAdapter;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Supports\Providers\DataTransferObjects\WebhookPayloadDTO;
use Exception;
use Illuminate\Support\Facades\Log;

final readonly class PaymentProvider
{
    public function __construct(private ProviderResolver $resolver) {}

    public function provide(string $channel): Provider
    {
        $providers = Provider::query()
            ->where('is_active', true)
            ->where('is_healthy', true)
            ->whereJsonContains('supported_channels', $channel)
            ->get();

        if ($providers->isEmpty()) {
            throw new Exception("No healthy providers available for channel: {$channel}");
        }

        //TODO: fix this to actually select by lowest fee
        return $providers->sortBy(function (Provider $provider) {
            return $provider->metadata['fee_percentage'] ?? 999;
        })->first();
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

    public function validate(Provider $provider, string $providerReference, PaymentValidateDTO $dto): ProviderResponse
    {
        return $this->resolveAdapter($provider)->validate($providerReference, $dto);
    }

    public function getFee(Provider $provider, FeeChannel $channel, int $amount): int
    {
        return $this->resolveAdapter($provider)->getFee($channel, $amount);
    }

    private function resolveAdapter(Provider $provider): ProviderAdapter
    {
        return $this->resolver->resolve($provider);
    }
}
