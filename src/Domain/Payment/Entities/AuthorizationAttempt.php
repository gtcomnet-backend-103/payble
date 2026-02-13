<?php

declare(strict_types=1);

namespace Domain\Payment\Entities;

use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use DateTimeImmutable;
use Support\ValueObjects\Ulid;

final readonly class AuthorizationAttempt
{
    public function __construct(
        public Ulid $id,
        public string $providerReference,
        public string $paymentIntentId,
        public string $providerId,
        public PaymentChannel $channel,
        public AuthorizationStatus $status,
        public int $amount,
        public string $currency,
        public int $fee,
        public int $providerFee,
        public string $idempotencyKey,
        public ?string $action = null,
        public ?array $rawResponse = null,
        public ?array $metadata = null,
        public ?DateTimeImmutable $completedAt = null,
        public ?DateTimeImmutable $createdAt = null,
        public ?DateTimeImmutable $updatedAt = null,
    ) {}

    public function isFinal(): bool
    {
        return $this->status->isFinal();
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id->toString(),
            'provider_reference' => $this->providerReference,
            'status' => $this->status->value,
            'channel' => $this->channel->value,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'fee' => $this->fee,
            'action' => $this->action,
            'metadata' => $this->metadata,
        ];
    }
}
