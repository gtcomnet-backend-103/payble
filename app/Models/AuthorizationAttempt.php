<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\FeeChannel;
use Eloquent;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * @property int $id
 * @property string $intent_type
 * @property int $intent_id
 * @property int $provider_id
 * @property FeeChannel $channel
 * @property string $provider_reference
 * @property AuthorizationStatus $status
 * @property \Carbon\CarbonImmutable|null $completed_at
 * @property int $fee
 * @property int $provider_fee
 * @property int $amount
 * @property string $currency
 * @property array<array-key, mixed>|null $raw_request
 * @property array<array-key, mixed>|null $raw_response
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read string|null $action
 * @property-read array $authorization
 * @property-read bool $completed
 * @property-read Model|Eloquent $intent
 * @property-read Provider $provider
 *
 * @method static Builder<static>|AuthorizationAttempt newModelQuery()
 * @method static Builder<static>|AuthorizationAttempt newQuery()
 * @method static Builder<static>|AuthorizationAttempt pending()
 * @method static Builder<static>|AuthorizationAttempt query()
 * @method static Builder<static>|AuthorizationAttempt validating()
 * @method static Builder<static>|AuthorizationAttempt whereAmount($value)
 * @method static Builder<static>|AuthorizationAttempt whereChannel($value)
 * @method static Builder<static>|AuthorizationAttempt whereCompletedAt($value)
 * @method static Builder<static>|AuthorizationAttempt whereCreatedAt($value)
 * @method static Builder<static>|AuthorizationAttempt whereCurrency($value)
 * @method static Builder<static>|AuthorizationAttempt whereFee($value)
 * @method static Builder<static>|AuthorizationAttempt whereId($value)
 * @method static Builder<static>|AuthorizationAttempt whereIntentId($value)
 * @method static Builder<static>|AuthorizationAttempt whereIntentType($value)
 * @method static Builder<static>|AuthorizationAttempt whereMetadata($value)
 * @method static Builder<static>|AuthorizationAttempt whereProviderFee($value)
 * @method static Builder<static>|AuthorizationAttempt whereProviderId($value)
 * @method static Builder<static>|AuthorizationAttempt whereProviderReference($value)
 * @method static Builder<static>|AuthorizationAttempt whereRawRequest($value)
 * @method static Builder<static>|AuthorizationAttempt whereRawResponse($value)
 * @method static Builder<static>|AuthorizationAttempt whereStatus($value)
 * @method static Builder<static>|AuthorizationAttempt whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
final class AuthorizationAttempt extends Model
{
    use HasFactory;

    protected $fillable = [
        'intent_id',
        'intent_type',
        'provider_id',
        'channel',
        'provider_reference',
        'status',
        'fee',
        'currency',
        'raw_request',
        'raw_response',
        'metadata',
        'provider_fee',
        'amount',
        'completed_at',
    ];

    /**
     * @param  Builder  $query
     * @return Builder
     */
    public function scopePending($query)
    {
        return $query->whereIn('status', [
            AuthorizationStatus::Pending,
            AuthorizationStatus::PendingTransfer,
            AuthorizationStatus::Success, // Optimistic success handling
        ]);
    }

    public function intent(): MorphTo
    {
        return $this->morphTo();
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function casts(): array
    {
        return [
            'channel' => FeeChannel::class,
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
        // Allow no-op if already in target status
        if ($this->status === $target) {
            return true;
        }

        if (! $this->status->canTransitionTo($target)) {
            throw new RuntimeException("Invalid status transition from {$this->status->value} to {$target->value}");
        }

        $data = ['status' => $target];

        return (bool) $this->update($data);
    }

    /**
     * @return Attribute<string|null, never>
     */
    public function action(): Attribute
    {
        return Attribute::get(function (): ?string {
            return match ($this->status) {
                AuthorizationStatus::PendingPhone => 'phone',
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
    public function completed(): Attribute
    {
        return Attribute::get(fn (): bool => $this->completed_at !== null);
    }

    /**
     * @return Attribute<array<string, mixed>, never>
     */
    public function authorization(): Attribute
    {
        return Attribute::get(
            fn (): array => $this->channel === FeeChannel::BANK_TRANSFER
                ? $this->raw_response['bank_details'] ?? []
                : []
        );
    }

    #[Scope]
    protected function validating(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AuthorizationStatus::PendingOtp,
            AuthorizationStatus::PendingPhone,
            AuthorizationStatus::PendingPin,
        ]);
    }
}
