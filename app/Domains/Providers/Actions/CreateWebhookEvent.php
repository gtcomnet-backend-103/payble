<?php

namespace App\Domains\Providers\Actions;

use App\Domains\Providers\Facades\PaymentProvider;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhook;

final class CreateWebhookEvent
{
    /**
     * @param Provider $provider
     * @param array<string, mixed> $payload
     * @return WebhookEvent
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
        return WebhookEvent::create([
            'provider' => $provider->identifier,
            'provider_event_id' => $webhookPayload->providerEventId,
            'event_type' => $webhookPayload->eventType,
            'raw_payload' => $payload,
            'received_at' => now(),
        ]);
    }
}
