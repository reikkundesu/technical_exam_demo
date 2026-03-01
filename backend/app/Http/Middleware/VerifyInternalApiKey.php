<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyInternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->header('X-Internal-Api-Key') ?? $request->query('api_key');

        $expectedKey = config('app.internal_api_key');
        if (empty($key) || empty($expectedKey) || ! hash_equals((string) $expectedKey, (string) $key)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
