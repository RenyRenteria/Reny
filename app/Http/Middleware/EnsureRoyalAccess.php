<?php

namespace App\Http\Middleware;

use App\Support\AccessStatePresenter;
use App\Support\EntitlementMatrix;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRoyalAccess
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        if (! EntitlementMatrix::canUseRoyalFeature($request->user())) {
            return response()->view('auth.permission-denied', [
                'section' => 'royal',
                'stateView' => AccessStatePresenter::for($request->user(), AccessStatePresenter::sourceFromRequest($request)),
            ], 403);
        }

        return $next($request);
    }
}
