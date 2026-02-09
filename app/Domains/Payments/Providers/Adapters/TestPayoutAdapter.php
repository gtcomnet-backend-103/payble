<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Adapters;

use App\Domains\Payments\Providers\Contracts\ProviderAdapter;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use RuntimeException;

final class TestPayoutAdapter implements ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse
    {
        throw new RuntimeException('TestPayoutAdapter cannot authorize payments.');
    }

    public function verifyWebhook(array $payload, array $headers): bool
    {
        return true;
    }

    public function normalizeWebhook(array $payload): WebhookPayloadDTO
    {
        throw new RuntimeException('TestPayoutAdapter cannot normalize webhooks.');
    }

    public function verifyTransaction(string $reference): ProviderResponse
    {
        return new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: $reference,
            rawResponse: ['status' => 'success']
        );
    }

    public function validate(string $providerReference, PaymentValidateDTO $dto): ProviderResponse
    {
        throw new RuntimeException('TestPayoutAdapter cannot validate payments.');
    }

    public function getFee(PaymentChannel $channel, int $amount): int
    {
        return 0;
    }

    public function initiateTransfer(PayoutTransferData $dto): ProviderResponse
    {
        return new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: 'TEST_'.$dto->reference,
            rawResponse: ['status' => 'success', 'message' => 'Test transfer successful', 'data' => []]
        );
    }
}
