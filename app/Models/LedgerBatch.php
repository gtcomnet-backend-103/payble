<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $transaction_id
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $posted_at
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 * @property-read \App\Models\Transaction|null $transaction
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch wherePostedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereTransactionId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|LedgerBatch whereUpdatedAt($value)
 * @mixin \Eloquent
 */
final class LedgerBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'posted_at',
        'metadata',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function isPosted(): bool
    {
        return $this->posted_at !== null;
    }

    public function markPosted(): void
    {
        $this->update(['posted_at' => now()]);
    }
}
