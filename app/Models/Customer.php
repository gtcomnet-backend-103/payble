<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'metadata',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function ledgerAccounts(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(LedgerAccount::class, 'holder');
    }
}
