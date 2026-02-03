<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

final class Provider extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'identifier',
        'is_active',
        'is_healthy',
        'supported_channels',
        'metadata',
    ];

    public function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_healthy' => 'boolean',
            'supported_channels' => 'array',
            'metadata' => 'array',
        ];
    }
}
