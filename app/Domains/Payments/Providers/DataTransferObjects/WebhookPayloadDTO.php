<?php

declare(strict_types=1);

namespace App\Domains\Payments\Providers\DataTransferObjects;

use App\Enums\AuthorizationStatus;

final class WebhookPayloadDTO
{
    public function __construct(
        public string $providerEventId,
        public string $eventType,
        public string $reference,
        public int $amount,
        public \App\Enums\Currency $currency,
        public AuthorizationStatus $status,
        public array $rawPayload,
        public array $metadata = []
    ) {}
}
