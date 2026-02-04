<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
