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
        RateLimiter::for('device', function (Request $request) {
            $key = $request->bearerToken() !== null
                ? hash('sha256', $request->bearerToken())
                : $request->ip();

            return Limit::perMinute(180)->by($key);
        });
    }
}
