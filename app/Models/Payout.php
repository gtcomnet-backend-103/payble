<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\Recordable;
use App\Domains\Ledger\DataTransferObjects\TransactionData;
use App\Enums\Currency;
use App\Enums\PaymentMode;
use App\Enums\PayoutStatus;
use App\Enums\PayoutType;
use Eloquent;
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
 * @property string $originator_type
 * @property int $originator_id
 * @property string|null $provider_reference
 * @property int $amount
 * @property int $fee
 * @property Currency $currency
 * @property PayoutType $type
 * @property PaymentMode $mode
 * @property PayoutStatus $status
 * @property string $reference
 * @property bool $requires_otp
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property int|null $bank_account_id
 * @property-read BankAccount|null $bankAccount
 * @property-read Business $business
 * @property-read mixed $net_amount
 * @property-read Model|Eloquent $originator
 * @property-read Provider|null $provider
 * @property-read Transaction|null $transaction
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBankAccountId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereOriginatorId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereOriginatorType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereProviderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereProviderReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereReference($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereRequiresOtp($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereStatus($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Payout whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Payout extends Model implements Recordable
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'provider_id',
        'type',
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
            'type' => PayoutType::class,
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
            fn() => max(0, $this->amount - $this->fee)
        );
    }

    protected function disbursementAmount(): Attribute
    {
        return Attribute::get(function () {
            if ($this->type !== PayoutType::Payout) {
                return $this->net_amount;
            }

            $currentDebt = \App\Domains\Ledger\Facades\Ledger::getBalance(
                \App\Domains\Ledger\Facades\Ledger::advance($this->business, $this->currency->value, $this->mode)
            );

            $settlementAmount = min($this->net_amount, $currentDebt);

            return max(0, $this->net_amount - $settlementAmount);
        });
    }
}
