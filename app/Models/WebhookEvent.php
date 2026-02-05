<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $provider
 * @property string|null $provider_event_id
 * @property string|null $event_type
 * @property array<array-key, mixed> $raw_payload
 * @property \Carbon\CarbonImmutable $received_at
 * @property \Carbon\CarbonImmutable|null $processed_at
 * @property string|null $feedback
 * @property \Carbon\CarbonImmutable|null $created_at
 * @property \Carbon\CarbonImmutable|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereEventType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereFeedback($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProcessedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereProviderEventId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereRawPayload($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereReceivedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|WebhookEvent whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
final class WebhookEvent extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'raw_payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }
}
