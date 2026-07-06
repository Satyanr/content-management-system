<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Login;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        $user = $event->user;

        if (session()->has('login_history_id')) {
            return;
        }

        LoginHistory::query()
            ->where('user_id', '=', $user->id)
            ->where('logout_at', '=', null)
            ->update([
                'logout_at' => now(),
            ]);

        $loginHistory = LoginHistory::query()->create([
            'company_id' => $user->company_id,
            'user_id' => $user->id,
            'session_id' => session()->getId(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'login_at' => now(),
            'last_activity_at' => now(),
        ]);

        session()->put('login_history_id', $loginHistory->id);
    }
}
