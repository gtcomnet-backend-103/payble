<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AccessLevel;
use App\Enums\PaymentMode;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ApiToken extends Model
{
    protected $fillable = [
        'business_id',
        'access_level',
        'mode',
        'lookup_key',
        'auth_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'access_level' => AccessLevel::class,
            'mode' => PaymentMode::class,
            'auth_key' => 'encrypted',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
