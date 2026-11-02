<?php

namespace App\Http\Middleware;

use App\Services\DeviceLifecycle;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $credential = app(DeviceLifecycle::class)->authenticate($request->bearerToken());
        if (! $credential) {
            return response()->json(['message' => 'Invalid device credential.'], 401);
        }
        $key = 'device-heartbeat:'.$credential->device_id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return response()->json(['message' => 'Heartbeat rate limit exceeded.'], 429)->header('Retry-After', (string) RateLimiter::availableIn($key));
        }
        RateLimiter::hit($key, 60);
        $request->attributes->set('device_credential', $credential);

        return $next($request);
    }
}
