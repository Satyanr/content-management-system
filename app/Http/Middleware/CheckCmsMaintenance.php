<?php

namespace App\Http\Middleware;

use Closure;
use App\Services\SettingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCmsMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $maintenanceMode = filter_var(app(SettingService::class)->get(key: 'maintenance_mode', default: false, companyId: null), FILTER_VALIDATE_BOOLEAN);

        if ($maintenanceMode) {
            return response()->view('errors.maintenance', [], 503);
        }

        return $next($request);
    }
}
