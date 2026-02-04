<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Providers\Actions\CreateWebhookEvent;
use App\Events\WebhookReceived;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __invoke(Request $request, Provider $provider, CreateWebhookEvent $action): JsonResponse
    {
        // 1. Persist event
        // Signature verification is handled by VerifyWebhookSignature middleware
        $event = $action->execute($provider, $request->all());

        // 2. Dispatch Event
        WebhookReceived::dispatch($event);

        return response()->json(['message' => 'Webhook received']);
    }
}
