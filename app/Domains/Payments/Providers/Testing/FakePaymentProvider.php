<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Testing;

use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Domains\Payments\Providers\Services\PaymentProvider;
use App\Models\Provider;
use PHPUnit\Framework\Assert;
use RuntimeException;

class FakePaymentProvider extends PaymentProvider
{
    /** @var array<string, ProviderResponse> */
    protected array $responses = [];

    /** @var array<int, array{provider: Provider, dto: PaymentAuthorizeDTO}> */
    protected array $recorded = [];

    protected ?WebhookPayloadDTO $webhookPayload = null;

    protected bool $webhookVerificationResult = true;

    protected int $fee = 0;

    public function __construct()
    {
        // No resolver needed for fake
    }

    public function authorize(Provider $provider, PaymentAuthorizeDTO $dto): ProviderResponse
    {
        $this->recorded[] = [
            'provider' => $provider,
            'dto' => $dto,
        ];

        return $this->responses[$dto->reference] ?? $this->responses['*'] ?? throw new RuntimeException("No fake response defined for reference: {$dto->reference}");
    }

    public function shouldReturn(ProviderResponse $response, ?string $reference = null): self
    {
        $this->responses[$reference ?? '*'] = $response;

        return $this;
    }

    public function assertAuthorized(string $reference): void
    {
        $found = collect($this->recorded)->first(fn ($item) => $item['dto']->reference === $reference);
        Assert::assertNotNull($found, "Payment with reference {$reference} was not authorized.");
    }

    public function verifyWebhook(Provider $provider, array $payload, array $headers): bool
    {
        return $this->webhookVerificationResult;
    }

    public function normalizeWebhook(Provider $provider, array $payload): WebhookPayloadDTO
    {
        return $this->webhookPayload ?? new WebhookPayloadDTO(
            providerEventId: 'evt_fake',
            eventType: 'charge.success',
            reference: $payload['data']['reference'] ?? 'ref_fake',
            amount: $payload['data']['amount'] ?? 0,
            currency: \App\Enums\Currency::tryFrom($payload['data']['currency'] ?? 'NGN') ?? \App\Enums\Currency::NGN,
            status: match ($payload['data']['status'] ?? 'success') {
                'success' => \App\Enums\AuthorizationStatus::Success,
                'failed' => \App\Enums\AuthorizationStatus::Failed,
                default => \App\Enums\AuthorizationStatus::Pending,
            },
            rawPayload: $payload
        );
    }

    public function shouldReturnWebhookPayload(WebhookPayloadDTO $payload): self
    {
        $this->webhookPayload = $payload;

        return $this;
    }

    public function shouldFailWebhookVerification(): self
    {
        $this->webhookVerificationResult = false;

        return $this;
    }

    public function verifyTransaction(Provider $provider, string $reference): ProviderResponse
    {
        // Mock response for verification
        return $this->responses[$reference] ?? $this->responses['*'] ?? new ProviderResponse(
            status: \App\Enums\AuthorizationStatus::Success,
            providerReference: 'ref_verified',
            rawResponse: ['status' => 'success']
        );
    }

    public function validate(Provider $provider, string $providerReference, \App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO $dto): ProviderResponse
    {
        $this->recorded[] = [
            'type' => 'validate',
            'provider' => $provider,
            'reference' => $providerReference,
            'data' => $dto->toArray(),
        ];

        return $this->responses[$providerReference] ?? $this->responses['*'] ?? throw new RuntimeException("No fake validate response defined for reference: {$providerReference}");
    }

    public function shouldReturnFee(int $fee): self
    {
        $this->fee = $fee;

        return $this;
    }

    public function getFee(Provider $provider, \App\Enums\PaymentChannel $channel, int $amount): int
    {
        return $this->fee;
    }
}
