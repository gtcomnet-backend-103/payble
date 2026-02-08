<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $business_id
 * @property AccessLevel $access_level
 * @property PaymentMode $mode
 * @property string $lookup_key
 * @property string $auth_key
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read Business $business
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereAccessLevel($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereAuthKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereBusinessId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereLookupKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereMode($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|ApiToken whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class ApiToken extends Model
{
    protected $fillable = [
        'business_id',
        'access_level',
        'mode',
        'lookup_key',
        'auth_key',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'access_level' => AccessLevel::class,
            'mode' => PaymentMode::class,
            'auth_key' => 'encrypted',
        ];
    }
}
