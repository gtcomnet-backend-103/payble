<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property string|null $holder_type
 * @property int|null $holder_id
 * @property AccountType $type
 * @property string $currency
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, LedgerEntry> $entries
 * @property-read int|null $entries_count
 * @property-read Model|Eloquent|null $holder
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereHolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereHolderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Account whereUpdatedAt($value)
 *
 * @mixin Eloquent
 */
final class Account extends Model
{
    use HasFactory;

    protected $table = 'ledger_accounts';

    protected $fillable = [
        'holder_id',
        'holder_type',
        'type',
        'currency',
        'mode',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'metadata' => 'array',
            'type' => AccountType::class,
            'mode' => \App\Enums\PaymentMode::class,
        ];
    }

    public function holder(): MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'ledger_account_id');
    }
}
