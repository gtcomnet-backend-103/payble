<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Domains\Payments\Events\WebhookReceived;
use App\Jobs\ProcessWebhook;

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
