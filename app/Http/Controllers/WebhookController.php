<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Payments\Actions\CreateWebhookEvent;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __invoke(Request $request, Provider $provider, CreateWebhookEvent $action): JsonResponse
    {
        // 1. Persist event and dispatch logic
        // Signature verification is handled by VerifyWebhookSignature middleware
        $action->execute($provider, $request->all());

        return response()->json(['message' => 'Webhook received']);
    }
}
