<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\TransactionStatus;
use Eloquent;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

/**
 * @property int $id
 * @property int $business_id
 * @property string $source_type
 * @property int $source_id
 * @property int $amount
 * @property int $fee
 * @property Currency $currency
 * @property TransactionStatus $status
 * @property string $reference
 * @property PaymentMode $mode
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property PaymentChannel $channel
 * @property-read Business $business
 * @property-read mixed $gross_amount
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @property-read Model|Eloquent $source
 *
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSourceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereSourceType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'currency',
        'amount',
        'fee',
        'status',
        'reference',
        'mode',
        'metadata',
        'source_id',
        'source_type',
        'provider_fee',
        'provider_reference',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function casts(): array
    {
        return [
            'currency' => Currency::class,
            'amount' => 'integer',
            'fee' => 'integer',
            'status' => TransactionStatus::class,
            'mode' => PaymentMode::class,
            'channel' => PaymentChannel::class,
            'metadata' => 'array',
        ];
    }

    public function transitionTo(TransactionStatus $target): bool
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

    protected function grossAmount(): Attribute
    {
        return Attribute::get(fn () => $this->amount + $this->fee);
    }

    protected function channel(): Attribute
    {
        // TODO: make the transaction aware of the channel
        return Attribute::get(function () {
            if ($this->source instanceof PaymentIntent) {
                return $this->source?->attempts()->where('status', \App\Enums\AuthorizationStatus::Success)->first()?->channel;
            }

            return null;
        });
    }
}
