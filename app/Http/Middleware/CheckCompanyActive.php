<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCompanyActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->hasRole('super-admin')) {
            return $next($request);
        }

        $company = $user->company;

        if (! $company || ! $company->is_active) {
            return response()->view('errors.company-inactive', [], 403);
        }

        return $next($request);
    }
}