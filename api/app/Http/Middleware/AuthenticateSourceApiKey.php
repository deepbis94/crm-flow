<?php

namespace App\Http\Middleware;

use App\Models\Source;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateSourceApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $plain = $request->header('X-Api-Key') ?: $request->bearerToken();

        if (! is_string($plain) || $plain === '') {
            return response()->json(['message' => 'Missing source API key.'], 401);
        }

        $source = Source::query()
            ->where('api_key_hash', Source::hashApiKey($plain))
            ->where('is_active', true)
            ->first();

        if (! $source) {
            return response()->json(['message' => 'Invalid source API key.'], 401);
        }

        $request->attributes->set('source', $source);

        return $next($request);
    }
}
