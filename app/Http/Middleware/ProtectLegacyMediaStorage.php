<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectLegacyMediaStorage
{
    public function handle(Request $request, Closure $next): Response
    {
        // Defense for requests reaching Laravel. Static web servers must deny this prefix too.
        abort_if($request->is('storage', 'storage/*'), 403, 'Direct public storage access is disabled.');

        return $next($request);
    }
}
