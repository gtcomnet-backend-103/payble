<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AuthenticateBusiness
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_contains($header, '_')) {
            return $next($request);
        }

        $key = str_replace('Bearer ', '', $header);
        $parts = explode('_', $key);

        if (count($parts) !== 3) {
            return $next($request);
        }

        [$prefix, $mode, $lookupKey] = $parts;

        /** @var ApiToken|null $token */
        $token = ApiToken::where('lookup_key', $lookupKey)->first();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        // Validate prefix and mode match the token record
        if (
            $token->mode->value !== $mode ||
            ($prefix === 'pk' && $token->access_level->value !== 'public') ||
            ($prefix === 'sk' && $token->access_level->value !== 'secret')
        ) {
            return response()->json(['message' => 'Invalid API Key format.'], 401);
        }

        // Inject the actual decrypted sanctum token
        // auth_key is cast to 'encrypted', so it's already decrypted when accessed
        $request->headers->set('Authorization', 'Bearer ' . $token->auth_key);

        // Set global mode for the request
        config(['app.payment_mode' => $token->mode->value]);

        return $next($request);
    }
}
