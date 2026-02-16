<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Recordable;
use App\Domains\Ledger\DataTransferObjects\TransactionData;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

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
 * @property int $fee
 * @property-read int $net_amount
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business $business
 * @property-read Provider|null $provider
 * @property-read Transaction|null $transaction
 *
 * @mixin \Eloquent
 */
final class Payout extends Model implements Recordable
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'provider_id',
        'amount',
        'fee',
        'currency',
        'currency',
        'mode',
        'status',
        'reference',
        'requires_otp',
        'metadata',
        'provider_reference',
    ];

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'fee' => 'integer',
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

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function originator(): MorphTo
    {
        return $this->morphTo();
    }

    public function toTransactionData(): TransactionData
    {
        return new TransactionData(
            businessId: $this->business_id,
            reference: $this->reference,
            amount: $this->amount,
            fee: $this->fee,
            currency: $this->currency,
            mode: $this->mode,
            metadata: $this->metadata,
        );
    }

    protected function netAmount(): Attribute
    {
        return Attribute::get(
            fn () => max(0, $this->amount - $this->fee)
        );
    }
}
