<?php

namespace App\Http\Middleware;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminSessionIsFresh
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $authenticatedAt = $request->session()->get('admin_authenticated_at');

        if (! is_numeric($authenticatedAt)) {
            return $this->expire($request);
        }

        $lifetime = max(1, (int) config('admin.session_lifetime_minutes', 120));
        $expiresAt = CarbonImmutable::createFromTimestamp((int) $authenticatedAt)->addMinutes($lifetime);

        if ($expiresAt->isPast()) {
            return $this->expire($request);
        }

        return $next($request);
    }

    private function expire(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with('status', 'Admin session expired. Sign in again to continue.');
    }
}
