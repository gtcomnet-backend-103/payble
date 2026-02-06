<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $payment_intent_id
 * @property int $provider_id
 * @property PaymentChannel $channel
 * @property string $provider_reference
 * @property AuthorizationStatus $status
 * @property int $fee
 * @property int $provider_fee
 * @property int $amount
 * @property string $currency
 * @property string $idempotency_key
 * @property array<array-key, mixed>|null $raw_request
 * @property array<array-key, mixed>|null $raw_response
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property-read string|null $action
 * @property-read array $authorization
 * @property-read array $bank_details
 * @property-read PaymentIntent $paymentIntent
 * @property-read Provider $provider
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt DQWpending()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereIdempotencyKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt wherePaymentIntentId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereRawRequest($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereRawResponse($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|AuthorizationAttempt whereCompletedAt($value)
 *
 * @mixin \Eloquent
 */
final class AuthorizationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_intent_id',
        'provider_id',
        'channel',
        'provider_reference',
        'status',
        'fee',
        'currency',
        'idempotency_key',
        'raw_request',
        'raw_response',
        'metadata',
        'provider_fee',
        'amount',
        'completed_at',
    ];

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            AuthorizationStatus::Pending,
            AuthorizationStatus::PendingTransfer,
            AuthorizationStatus::Success, // Optimistic success handling
        ]);
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValidating($query)
    {
        return $query->whereIn('status', [
            AuthorizationStatus::PendingOtp,
            AuthorizationStatus::PendingPhone,
            AuthorizationStatus::PendingPin, // Optimistic success handling
        ]);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'status' => AuthorizationStatus::class,
            'fee' => 'integer',
            'raw_request' => 'array',
            'raw_response' => 'array',
            'metadata' => 'array',
            'completed_at' => 'immutable_datetime',
        ];
    }

    public function markAsComplete(): bool
    {
        return $this->update([
            'completed_at' => now(),
        ]);
    }

    public function transitionTo(AuthorizationStatus $target): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new RuntimeException("Invalid status transition from {$this->status->value} to {$target->value}");
        }

        $data = ['status' => $target];

        return (bool) $this->update($data);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function action(): Attribute
    {
        return Attribute::get(function (): ?string {
            return match ($this->status) {
                AuthorizationStatus::PendingPin => 'pin',
                AuthorizationStatus::PendingOtp => 'otp',
                AuthorizationStatus::PendingTransfer => 'transfer',
                default => null,
            };
        });
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function completed(): Attribute
    {
        return Attribute::get(fn (): bool => $this->completed_at !== null);
    }

    /**
     * @return Attribute<array<string, mixed>, never>
     */
    protected function authorization(): Attribute
    {
        return Attribute::get(
            fn (): array => $this->channel === PaymentChannel::BankTransfer
                ? $this->raw_response['bank_details'] ?? []
                : []
        );
    }
}
