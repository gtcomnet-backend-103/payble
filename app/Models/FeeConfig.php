<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $business_id
 * @property PaymentChannel $channel
 * @property int $min_fee
 * @property int|null $max_fee
 * @property numeric $percentage
 * @property int $fixed_amount
 * @property bool $is_active
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business|null $business
 *
 * @method static \Database\Factories\FeeConfigFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereChannel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereFixedAmount($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereMaxFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereMinFee($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig wherePercentage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|FeeConfig whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class FeeConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'channel',
        'min_fee',
        'max_fee',
        'percentage',
        'fixed_amount',
        'is_active',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function casts(): array
    {
        return [
            'channel' => PaymentChannel::class,
            'min_fee' => 'integer',
            'max_fee' => 'integer',
            'percentage' => 'decimal:2',
            'fixed_amount' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
