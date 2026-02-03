<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentChannel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
