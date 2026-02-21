<?php

declare(strict_types=1);

namespace App\Supports\Providers\Adapters;

use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\AuthorizationStatus;
use App\Enums\FeeChannel;
use App\Enums\PaymentChannel;
use App\Supports\Providers\Contracts\ProviderAdapter;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Supports\Providers\DataTransferObjects\WebhookPayloadDTO;
use RuntimeException;

final class TestAdapter implements ProviderAdapter
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

    public function getFee(FeeChannel $channel, int $amount): int
    {
        return 0;
    }

    public function initiateTransfer(PayoutTransferData $dto): ProviderResponse
    {
        return new ProviderResponse(
            status: AuthorizationStatus::Success,
            providerReference: 'TEST_' . $dto->reference,
            rawResponse: ['status' => 'success', 'message' => 'Test transfer successful', 'data' => []]
        );
    }

    public function verifyTransfer(string $reference): ProviderResponse
    {
        return $this->verifyTransaction($reference);
    }

    public function listBanks(): array
    {
        return [
            ['name' => 'Test Bank', 'code' => '000'],
        ];
    }
}
