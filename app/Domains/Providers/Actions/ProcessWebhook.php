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
        // 1. Normalize payload
        $normalized = PaymentProvider::normalizeWebhook($provider, $payload);

        // 3. Idempotency Check (Webhook-Level)
        if ($normalized->providerEventId) {
            $exists = WebhookEvent::where('provider', $provider->identifier)
                ->where('provider_event_id', $normalized->providerEventId)
                ->exists();

            if ($exists) {
                return;
            }
        }

        // 4. Persist Raw Event
        $event = WebhookEvent::create([
            'provider' => $provider->identifier,
            'provider_event_id' => $normalized->providerEventId,
            'event_type' => $normalized->eventType,
            'raw_payload' => $payload,
            'received_at' => now(),
        ]);

        // 5. Dispatch async job for state evaluation & ledger
        HandleProviderWebhook::dispatch($event->id);
    }
}
