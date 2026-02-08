<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class LedgerBatch extends Model
{
    use HasFactory;

    protected $fillable = [
        'transaction_id',
        'posted_at',
    ];

    protected $casts = [
        'posted_at' => 'datetime',
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
