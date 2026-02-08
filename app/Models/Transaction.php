<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use RuntimeException;

/**
 * @property int $id
 * @property int $business_id
 * @property int $amount
 * @property Currency $currency
 * @property TransactionStatus $status
 * @property string $reference
 * @property PaymentMode $mode
 * @property PaymentChannel|null $channel
 * @property int $fees
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @property-read PaymentIntent|null $paymentIntent
 *
 * @method static \Database\Factories\TransactionFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereReference($value)
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
        'amount',
        'currency',
        'status',
        'reference',
        'mode',
        'channel',
        'fees',
        'metadata',
        'gross_amount',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class, 'reference', 'reference');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'fees' => 'integer',
            'currency' => Currency::class,
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
}
