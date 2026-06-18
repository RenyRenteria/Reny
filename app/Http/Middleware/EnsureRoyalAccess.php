<?php

namespace App\Http\Middleware;

use App\Support\AccountStateView;
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
                'message' => 'This route checks Royal Pass before it renders protected content.',
                'section' => 'royal',
                'state' => AccountStateView::for($request->user()),
                'title' => 'Royal Pass required',
            ], 403);
        }

        return $next($request);
    }
}
