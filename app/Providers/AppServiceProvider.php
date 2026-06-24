<?php

namespace App\Providers;

use App\Support\SecurityRateLimitKey;
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
        RateLimiter::for('analytics-events', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?: 'anonymous');
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(5)->by(SecurityRateLimitKey::auth($request));
        });

        RateLimiter::for('admin-auth', function (Request $request) {
            return Limit::perMinute(3)->by(SecurityRateLimitKey::adminAuth($request));
        });

        RateLimiter::for('checkout', function (Request $request) {
            return Limit::perMinute(20)->by(SecurityRateLimitKey::checkout($request));
        });

        RateLimiter::for('community', function (Request $request) {
            return Limit::perMinute(30)->by(SecurityRateLimitKey::community($request));
        });
    }
}
