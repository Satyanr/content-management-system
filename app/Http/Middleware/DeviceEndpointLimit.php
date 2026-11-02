<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class DeviceEndpointLimit
{
    public function handle(Request $request, Closure $next, string $endpoint): Response
    {
        $credential = $request->attributes->get('device_credential');
        abort_unless($credential, 401);
        $limit = match ($endpoint) {
            'heartbeat' => 10, 'manifest' => 30, default => 600
        };
        $key = 'device-'.$endpoint.':'.$credential->device_id;
        if (RateLimiter::tooManyAttempts($key, $limit)) {
            return response()->json(['message' => 'Device rate limit exceeded.', 'error' => ['code' => 'rate_limited']], 429)
                ->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);

        return $next($request);
    }
}
