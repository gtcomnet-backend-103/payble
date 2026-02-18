<?php

declare(strict_types=1);

namespace Infrastructure\Payment\Persistence;

use App\Models\AuthorizationAttempt as EloquentAttempt;
use App\Models\PaymentIntent as EloquentPayment;
use DateTimeImmutable;
use Domain\Payment\Entities\AuthorizationAttempt;
use Domain\Payment\Entities\PaymentRequest;
use Support\ValueObjects\Ulid;

final class EloquentAuthorizationRepository implements AuthorizationRepositoryInterface
{
    public function save(AuthorizationAttempt $attempt): void
    {
        EloquentAttempt::query()->updateOrCreate(
            ['id' => $attempt->id->toString()],
            [
                'provider_reference' => $attempt->providerReference,
                'source_id' => $attempt->paymentIntentId,
                'source_type' => 'App\Models\PaymentIntent',
                'provider_id' => $attempt->providerId,
                'channel' => $attempt->channel,
                'status' => $attempt->status,
                'amount' => $attempt->amount,
                'currency' => $attempt->currency,
                'fee' => $attempt->fee,
                'provider_fee' => $attempt->providerFee,
                'idempotency_key' => $attempt->idempotencyKey,
                'action' => $attempt->action,
                'raw_response' => $attempt->rawResponse,
                'metadata' => $attempt->metadata,
                'completed_at' => $attempt->completedAt?->format('Y-m-d H:i:s'),
            ]
        );
    }

    public function findByReference(string $reference): ?PaymentRequest
    {
        $eloquent = EloquentPayment::query()
            ->where('reference', $reference)
            ->first();

        if (! $eloquent) {
            return null;
        }

        return (new EloquentPaymentRepository())->toDomain($eloquent);
    }

    public function findExistingAttempt(string $paymentIntentId, string $channel): ?AuthorizationAttempt
    {
        $eloquent = EloquentAttempt::query()
            ->where('source_id', (int) $paymentIntentId)
            ->where('source_type', 'App\Models\PaymentIntent')
            ->where('channel', $channel)
            ->first();

        if (! $eloquent) {
            return null;
        }

        return $this->mapToDomain($eloquent);
    }

    public function lockPaymentRequest(string $reference): ?PaymentRequest
    {
        $eloquent = EloquentPayment::query()
            ->where('reference', $reference)
            ->lockForUpdate()
            ->first();

        if (! $eloquent) {
            return null;
        }

        return (new EloquentPaymentRepository())->toDomain($eloquent);
    }

    private function mapToDomain(EloquentAttempt $eloquent): AuthorizationAttempt
    {
        return new AuthorizationAttempt(
            id: new Ulid($eloquent->id),
            providerReference: $eloquent->provider_reference,
            paymentIntentId: (string) $eloquent->source_id,
            providerId: (string) $eloquent->provider_id,
            channel: $eloquent->channel,
            status: $eloquent->status,
            amount: $eloquent->amount,
            currency: $eloquent->currency,
            fee: $eloquent->fee,
            providerFee: (int) $eloquent->provider_fee,
            idempotencyKey: $eloquent->idempotency_key,
            action: $eloquent->action,
            rawResponse: $eloquent->raw_response,
            metadata: $eloquent->metadata,
            completedAt: $eloquent->completed_at ? new DateTimeImmutable($eloquent->completed_at->toDateTimeString()) : null,
            createdAt: new DateTimeImmutable($eloquent->created_at->toDateTimeString()),
            updatedAt: new DateTimeImmutable($eloquent->updated_at->toDateTimeString()),
        );
    }
}
