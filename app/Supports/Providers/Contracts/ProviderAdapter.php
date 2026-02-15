<?php

declare(strict_types=1);

namespace App\Supports\Providers\Contracts;

use App\Domains\Payouts\DataTransferObjects\PayoutTransferData;
use App\Enums\PaymentChannel;
use App\Supports\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Supports\Providers\DataTransferObjects\PaymentValidateDTO;
use App\Supports\Providers\DataTransferObjects\ProviderResponse;
use App\Supports\Providers\DataTransferObjects\WebhookPayloadDTO;

interface ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse;

    public function verifyWebhook(array $payload, array $headers): bool;

    public function normalizeWebhook(array $payload): WebhookPayloadDTO;

    public function verifyTransaction(string $reference): ProviderResponse;

    public function validate(string $providerReference, PaymentValidateDTO $dto): ProviderResponse;

    public function getFee(PaymentChannel $channel, int $amount): int;

    public function initiateTransfer(PayoutTransferData $dto): ProviderResponse;

    public function verifyTransfer(string $reference): ProviderResponse;
}
