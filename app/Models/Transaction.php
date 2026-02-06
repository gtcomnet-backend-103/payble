<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $business_id
 * @property int $payment_intent_id
 * @property int $amount
 * @property Currency $currency
 * @property PaymentStatus $status
 * @property string $reference
 * @property PaymentMode $mode
 * @property PaymentChannel|null $channel
 * @property string|null $ip_address
 * @property int $fees
 * @property array<array-key, mixed>|null $metadata
 * @property array<array-key, mixed>|null $authorization
 * @property string|null $message
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business $business
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $ledgerEntries
 * @property-read int|null $ledger_entries_count
 * @property-read PaymentIntent $paymentIntent
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereAuthorization($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereFees($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereIpAddress($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Transaction wherePaymentIntentId($value)
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
        'payment_intent_id',
        'amount',
        'currency',
        'status',
        'reference',
        'mode',
        'channel',
        'ip_address',
        'fees',
        'metadata',
        'authorization',
        'message',
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
            'authorization' => 'array',
        ];
    }

    public function transitionTo(TransactionStatus $target): bool
    {
        if (! $this->status->canTransitionTo($target)) {
            throw new \RuntimeException("Invalid status transition from {$this->status->value} to {$target->value}");
        }

        return (bool) $this->update(['status' => $target]);
    }
}
