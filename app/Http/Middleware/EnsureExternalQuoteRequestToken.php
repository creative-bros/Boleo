<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExternalQuoteRequestToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) config('services.external_quote_requests.token', '');

        if ($configuredToken === '') {
            return response()->json([
                'message' => 'El token de integracion no esta configurado.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $requestToken = (string) $request->bearerToken();

        if ($requestToken === '' || ! hash_equals($configuredToken, $requestToken)) {
            return response()->json([
                'message' => 'No autorizado.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
