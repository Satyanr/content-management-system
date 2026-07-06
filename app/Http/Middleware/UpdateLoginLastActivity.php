<?php

namespace App\Http\Middleware;

use App\Models\LoginHistory;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class UpdateLoginLastActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() && session()->has('login_history_id')) {
            LoginHistory::query()
                ->where('id', '=', session()->get('login_history_id'))
                ->where('logout_at', '=', null)
                ->update([
                    'last_activity_at' => now(),
                ]);
        }

        return $next($request);
    }
}