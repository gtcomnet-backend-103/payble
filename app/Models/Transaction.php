<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Currency;
use App\Enums\PaymentChannel;
use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'payment_intent_id',
        'amount',
        'currency',
        'status',
        'reference',
        'mode',
        'channel',
        'ip_address',
        'fees',
        'metadata',
        'authorization',
        'message',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function paymentIntent(): BelongsTo
    {
        return $this->belongsTo(PaymentIntent::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    public function casts(): array
    {
        return [
            'amount' => 'integer',
            'fees' => 'integer',
            'currency' => Currency::class,
            'status' => PaymentStatus::class,
            'mode' => PaymentMode::class,
            'channel' => PaymentChannel::class,
            'metadata' => 'array',
            'authorization' => 'array',
        ];
    }
}
