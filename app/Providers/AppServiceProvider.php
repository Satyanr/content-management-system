<?php

namespace App\Providers;

use App\Listeners\LogSuccessfulLogin;
use App\Listeners\LogSuccessfulLogout;
use App\Services\DeviceLifecycle;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('device-activate', function (Request $request) {
            return [Limit::perMinute(5)->by('activate-ip:'.$request->ip()),
                Limit::perMinute(5)->by('activate-code:'.hash('sha256', DeviceLifecycle::normalizeCode($request->input('activation_code'))))];
        });
        RateLimiter::for('device-ingress', fn (Request $request) => Limit::perMinute(120)->by('device-ip:'.$request->ip()));
        RateLimiter::for('device-player-ingress', fn (Request $request) => Limit::perMinute(1200)->by('device-player-ip:'.$request->ip()));
        Event::listen(Login::class, LogSuccessfulLogin::class);
        Event::listen(Logout::class, LogSuccessfulLogout::class);
    }
}
