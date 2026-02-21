<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Actions;

use App\Contracts\IdempotentAction;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Supports\Providers\Facades\PaymentProvider;
use App\Domains\Webhooks\Events\WebhookReceived;

final class CreateWebhookEvent implements IdempotentAction
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function execute(Provider $provider, array $payload): WebhookEvent
    {
        // Normalize payload
        $webhookPayload = PaymentProvider::normalizeWebhook($provider, $payload);

        // Idempotency Check (Webhook-Level)
        if ($webhookPayload->providerEventId) {
            $event = WebhookEvent::query()->where('provider', $provider->identifier)
                ->where('provider_event_id', $webhookPayload->providerEventId)
                ->first();

            if ($event) {
                return $event;
            }
        }

        // Persist Raw Event
        $event = WebhookEvent::create([
            'provider' => $provider->identifier,
            'provider_event_id' => $webhookPayload->providerEventId,
            'event_type' => $webhookPayload->eventType,
            'raw_payload' => $payload,
            'received_at' => now(),
        ]);

        WebhookReceived::dispatch($event);

        return $event;
    }
}
