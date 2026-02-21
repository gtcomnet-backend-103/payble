<?php

declare(strict_types=1);

namespace App\Domains\Webhooks\Listeners;

use App\Domains\Webhooks\Events\WebhookReceived;
use App\Domains\Webhooks\Jobs\ProcessWebhook;

final class DispatchWebhookProcessing
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(WebhookReceived $event): void
    {
        ProcessWebhook::dispatch($event->event->id);
    }
}
