<?php

use App\Http\Middleware\AuthenticateDevice;
use App\Http\Middleware\CheckCmsMaintenance;
use App\Http\Middleware\CheckCompanyActive;
use App\Http\Middleware\DeviceRequestGuard;
use App\Http\Middleware\ProtectLegacyMediaStorage;
use App\Http\Middleware\UpdateLoginLastActivity;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(web: __DIR__.'/../routes/web.php', api: __DIR__.'/../routes/api.php', commands: __DIR__.'/../routes/console.php', health: '/up')
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(ProtectLegacyMediaStorage::class);
        $middleware->prependToPriorityList(ThrottleRequests::class, DeviceRequestGuard::class);
        $middleware->alias([
            'device.payload' => DeviceRequestGuard::class,
            'device.auth' => AuthenticateDevice::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'cms.maintenance' => CheckCmsMaintenance::class,
            'login.activity' => UpdateLoginLastActivity::class,
            'company.active' => CheckCompanyActive::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['activation_code', 'credential', 'token', 'token_hash', 'code_hash']);
        $exceptions->shouldRenderJsonWhen(fn (Request $request) => $request->is('api/*'));
    })
    ->create();
