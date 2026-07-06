<?php

namespace App\Listeners;

use App\Models\LoginHistory;
use Illuminate\Auth\Events\Logout;

class LogSuccessfulLogout
{
    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (!$user) {
            return;
        }

        $loginHistoryId = session()->get('login_history_id');

        if ($loginHistoryId) {
            LoginHistory::query()
                ->where('id', '=', $loginHistoryId)
                ->where('logout_at', '=', null)
                ->update([
                    'last_activity_at' => now(),
                    'logout_at' => now(),
                ]);

            session()->forget('login_history_id');

            return;
        }

        $loginHistory = LoginHistory::query()->where('user_id', '=', $user->id)->where('logout_at', '=', null)->orderBy('login_at', 'desc')->first();

        if ($loginHistory) {
            $loginHistory->update([
                'last_activity_at' => now(),
                'logout_at' => now(),
            ]);
        }
    }
}
