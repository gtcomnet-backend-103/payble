<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property int $customer_id
 * @property int $amount
 * @property Currency $currency
 * @property string $reference
 * @property PaymentStatus $status
 * @property FeeBearer $bearer
 * @property PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AuthorizationAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read Business $business
 * @property-read Customer $customer
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Transaction> $transactions
 * @property-read int|null $transactions_count
 *
 * @method static \Database\Factories\PaymentIntentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereBearer($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereCustomerId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class PaymentIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'amount',
        'currency',
        'reference',
        'status',
        'bearer',
        'mode',
        'metadata',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AuthorizationAttempt::class);
    }

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'currency' => Currency::class,
            'status' => PaymentStatus::class,
            'bearer' => FeeBearer::class,
            'mode' => PaymentMode::class,
            'metadata' => 'array',
        ];
    }

    public function transitionTo(PaymentStatus $target): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new \RuntimeException("Invalid status transition from {$this->status->value} to {$target->value}");
        }

        return (bool) $this->update(['status' => $target]);
    }
}
