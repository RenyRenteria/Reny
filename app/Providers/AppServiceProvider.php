<?php

namespace App\Providers;

use App\Services\CmsPreviewContext;
use App\Support\SecurityRateLimits;
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
        $this->app->singleton(CmsPreviewContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for(SecurityRateLimits::PUBLIC_LOGIN, function (Request $request) {
            return Limit::perMinute(5)->by(SecurityRateLimits::publicLoginKey($request));
        });

        RateLimiter::for(SecurityRateLimits::ADMIN_LOGIN, function (Request $request) {
            return Limit::perMinute(3)->by(SecurityRateLimits::adminLoginKey($request));
        });

        RateLimiter::for(SecurityRateLimits::PASSWORD_RESET, function (Request $request) {
            return Limit::perMinutes(10, 3)->by(SecurityRateLimits::passwordResetKey($request));
        });

        RateLimiter::for(SecurityRateLimits::CHECKOUT, function (Request $request) {
            return Limit::perMinute(20)->by(SecurityRateLimits::checkoutKey($request));
        });

        RateLimiter::for('analytics-events', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip() ?: 'anonymous');
        });

        RateLimiter::for('community-writes', function (Request $request) {
            return Limit::perMinute(30)->by($request->user()?->id ?: ($request->ip() ?: 'anonymous'));
        });

        RateLimiter::for('community-chat', function (Request $request) {
            return Limit::perMinute(8)->by($request->user()?->id ?: ($request->ip() ?: 'anonymous'));
        });
    }
}
