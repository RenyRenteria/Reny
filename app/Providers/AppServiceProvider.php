<?php

namespace App\Providers;

use App\Services\CmsPreviewContext;
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
