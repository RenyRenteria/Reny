<?php

use App\Http\Middleware\EnsureAdminAccess;
use App\Http\Middleware\EnsureAdminCanPublish;
use App\Http\Middleware\EnsureAdminCmsIsEnabled;
use App\Http\Middleware\EnsureAdminSessionIsFresh;
use App\Http\Middleware\EnsureRoyalAccess;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'admin.access' => EnsureAdminAccess::class,
            'admin.cms' => EnsureAdminCmsIsEnabled::class,
            'admin.publish' => EnsureAdminCanPublish::class,
            'admin.session' => EnsureAdminSessionIsFresh::class,
            'royal' => EnsureRoyalAccess::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'analytics/events',
            'paypal/*',
            'mux/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
