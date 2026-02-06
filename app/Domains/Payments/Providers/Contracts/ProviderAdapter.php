<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\Contracts;

use App\Domains\Payments\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Payments\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Payments\Providers\DataTransferObjects\WebhookPayloadDTO;
use App\Enums\PaymentChannel;

interface ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse;

    public function verifyWebhook(array $payload, array $headers): bool;

    public function normalizeWebhook(array $payload): WebhookPayloadDTO;

    public function verifyTransaction(string $reference): ProviderResponse;

    public function validate(string $providerReference, \App\Domains\Payments\Providers\DataTransferObjects\PaymentValidateDTO $dto): ProviderResponse;

    public function getFee(PaymentChannel $channel, int $amount): int;
}
