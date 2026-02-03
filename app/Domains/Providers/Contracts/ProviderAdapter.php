<?php

declare(strict_types=1);

namespace App\Domains\Providers\Contracts;

use App\Domains\Providers\DataTransferObjects\PaymentAuthorizeDTO;
use App\Domains\Providers\DataTransferObjects\ProviderResponse;
use App\Domains\Providers\DataTransferObjects\WebhookPayloadDTO;

interface ProviderAdapter
{
    public function authorize(PaymentAuthorizeDTO $dto): ProviderResponse;

    public function verifyWebhook(array $payload, array $headers): bool;

    public function normalizeWebhook(array $payload): WebhookPayloadDTO;

    public function verifyTransaction(string $reference): ProviderResponse;

    public function getFee(\App\Enums\PaymentChannel $channel, int $amount): int;
}
