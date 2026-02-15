<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Provider;
use App\Supports\Providers\Facades\PaymentProvider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');

        // Ensure provider is resolved (Route Model Binding should handle this, but for safety)
        if (! $provider instanceof Provider) {
            abort(404, 'Provider not found');
        }

        $payload = $request->all();
        $headers = collect($request->headers->all())->map(fn ($h) => $h[0])->toArray();

        if (! PaymentProvider::verifyWebhook($provider, $payload, $headers)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
