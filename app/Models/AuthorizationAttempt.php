<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AuthorizationStatus;
use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AuthorizationAttempt extends Model
{
    use HasFactory;

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
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
    ];

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
        ];
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
     * @return Attribute<array<string, mixed>, never>
     */
    protected function bankDetails(): Attribute
    {
        return Attribute::get(fn(): array => $this->raw_response['bank_details'] ?? []);
    }

    /**
     * @return Attribute<array<string, mixed>, never>
     */
    protected function authorization(): Attribute
    {
        return Attribute::get(
            fn(): array => $this->channel === PaymentChannel::BankTransfer
                ? $this->bank_details
                : []
        );
    }
}
