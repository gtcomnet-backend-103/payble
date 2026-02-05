<?php

namespace App\Http\Middleware;

use App\Domains\Payments\Providers\Facades\PaymentProvider;
use App\Models\Provider;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class VerifyWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provider = $request->route('provider');

        // Ensure provider is resolved (Route Model Binding should handle this, but for safety)
        if (! $provider instanceof Provider) {
            abort(404, 'Provider not found');
        }

        $payload = $request->all();
        $headers = collect($request->headers->all())->map(fn($h) => $h[0])->toArray();

        if (! PaymentProvider::verifyWebhook($provider, $payload, $headers)) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        return $next($request);
    }
}
