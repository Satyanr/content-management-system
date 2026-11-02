<?php

namespace App\Http\Middleware;

use App\Services\DeviceLifecycle;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $credential = app(DeviceLifecycle::class)->identify($request->bearerToken());
        if (! $credential) {
            return response()->json(['message' => 'Invalid device credential.', 'error' => ['code' => 'invalid_token']], 401);
        }
        if ($credential->device->status !== 'active') {
            return response()->json(['message' => 'Device is unavailable.', 'error' => ['code' => $credential->device->status === 'disabled' ? 'device_disabled' : 'invalid_token']], 401);
        }
        if (! $credential->device->company->is_active) {
            return response()->json(['message' => 'Company is unavailable.', 'error' => ['code' => 'company_inactive']], 401);
        }
        $request->attributes->set('device_credential', $credential);

        return $next($request);
    }
}
