<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Eloquent;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereCurrency($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereHolderId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereHolderType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerAccount whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class LedgerAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'holder_id',
        'holder_type',
        'type',
        'currency',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'metadata' => 'array',
            'type' => AccountType::class,
        ];
    }

    public function holder(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo();
    }

    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function balance(): int
    {
        return (int) $this->entries()->sum('amount');
    }
}
