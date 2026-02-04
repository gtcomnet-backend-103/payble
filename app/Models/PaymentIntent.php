<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\FeeBearer;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

final class PaymentIntent extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'amount',
        'currency',
        'reference',
        'status',
        'bearer',
        'mode',
        'metadata',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(AuthorizationAttempt::class);
    }

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'currency' => Currency::class,
            'status' => PaymentStatus::class,
            'bearer' => FeeBearer::class,
            'mode' => PaymentMode::class,
            'metadata' => 'array',
        ];
    }
}
