<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

/**
 * @property int $id
 * @property int $business_id
 * @property int|null $provider_id
 * @property int $amount
 * @property Currency $currency
 * @property PaymentMode $mode
 * @property PayoutStatus $status
 * @property string $reference
 * @property bool $requires_otp
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business $business
 * @property-read Provider|null $provider
 * @property-read Transaction|null $transaction
 *
 * @mixin \Eloquent
 */
final class Payout extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'provider_id',
        'amount',
        'currency',
        'mode',
        'status',
        'reference',
        'requires_otp',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'currency' => Currency::class,
            'mode' => PaymentMode::class,
            'status' => PayoutStatus::class,
            'requires_otp' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'source');
    }
}
