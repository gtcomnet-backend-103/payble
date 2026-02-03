<?php

namespace App\Domains\Providers\Actions;

use App\Domains\Providers\Facades\PaymentProvider;
use App\Models\Provider;
use App\Models\WebhookEvent;
use App\Jobs\HandleProviderWebhook;

final class ProcessWebhook
{
    public function execute(Provider $provider, array $payload): void
    {
        // Normalize payload
        $webhookPayload = PaymentProvider::normalizeWebhook($provider, $payload);

        // Idempotency Check (Webhook-Level)
        if ($webhookPayload->providerEventId) {
            $exists = WebhookEvent::where('provider', $provider->identifier)
                ->where('provider_event_id', $webhookPayload->providerEventId)
                ->exists();

            if ($exists) {
                return;
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

        // Dispatch async job for state evaluation & ledger
        HandleProviderWebhook::dispatch($event->id);
    }
}
