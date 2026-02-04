<?php

namespace App\Listeners;

use App\Events\WebhookReceived;
use App\Jobs\ProcessWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class DispatchWebhookProcessing
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
