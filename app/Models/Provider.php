<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $identifier
 * @property bool $is_active
 * @property bool $is_healthy
 * @property array<array-key, mixed> $supported_channels
 * @property array<array-key, mixed>|null $metadata
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 *
 * @method static \Database\Factories\ProviderFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIdentifier($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIsActive($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereIsHealthy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereMetadata($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereSupportedChannels($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Provider whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
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
