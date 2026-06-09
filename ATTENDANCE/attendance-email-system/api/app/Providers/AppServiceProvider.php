<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        // 60 req/min for authenticated callers, 20 req/min for anonymous ones
        RateLimiter::for('api', function (Request $request) {
            $user = $request->user('api');

            return $user
                ? Limit::perMinute(60)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(20)->by('ip:' . $request->ip());
        });
    }
}
