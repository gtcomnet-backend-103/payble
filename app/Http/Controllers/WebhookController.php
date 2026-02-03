<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domains\Providers\Actions\ProcessWebhook;
use App\Domains\Providers\Facades\PaymentProvider;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WebhookController
{
    public function __invoke(Request $request, string $providerIdentifier): JsonResponse
    {
        $provider = Provider::where('identifier', $providerIdentifier)->first();

        if (! $provider) {
            return response()->json(['message' => 'Provider not found'], 404);
        }

        $payload = $request->all();
        $headers = collect($request->headers->all())->map(fn ($h) => $h[0])->toArray();

        // 1. Signature & Authenticity Validation
        if (! PaymentProvider::verifyWebhook($provider, $payload, $headers)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // 2. Persist & Process (Async via Action)
        // We'll create this action next
        app(ProcessWebhook::class)->execute($provider, $payload);

        return response()->json(['message' => 'Webhook received']);
    }
}
