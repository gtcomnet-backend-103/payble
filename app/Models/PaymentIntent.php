<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Recordable;
use App\Domains\Ledger\DataTransferObjects\TransactionData;
use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use RuntimeException;

/**
 * @property int $id
 * @property int $business_id
 * @property int $customer_id
 * @property int $amount
 * @property int $amount_paid
 * @property Currency $currency
 * @property string $reference
 * @property PaymentStatus $status
 * @property FeeBearer $bearer
 * @property PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\AuthorizationAttempt> $attempts
 * @property-read int|null $attempts_count
 * @property-read \App\Models\Business $business
 * @property-read \App\Models\Customer $customer
 * @property-read \App\Models\Transaction|null $transaction
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Transaction> $transactions
 * @property-read int|null $transactions_count
 * @method static \Database\Factories\PaymentIntentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|PaymentIntent whereAmountPaid($value)
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
 * @mixin \Eloquent
 */
final class PaymentIntent extends Model implements Recordable
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
        'amount_paid',
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

    public function attempts(): MorphMany
    {
        return $this->morphMany(AuthorizationAttempt::class, 'intent');
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
        // Allow no-op if already in target status
        if ($this->status === $target) {
            return true;
        }

        if (! $this->status->canTransitionTo($target)) {
            throw new RuntimeException("Invalid status transition from {$this->status->value} to {$target->value}");
        }

        return (bool) $this->update(['status' => $target]);
    }

    public function transaction(): MorphOne
    {
        return $this->morphOne(Transaction::class, 'source');
    }

    public function toTransactionData(): TransactionData
    {
        return new TransactionData(
            businessId: $this->business_id,
            reference: $this->reference,
            amount: $this->amount,
            fee: 0,
            currency: $this->currency,
            mode: $this->mode,
            metadata: $this->metadata,
        );
    }
}
