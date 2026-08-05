<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityRateLimitKeys;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class AdminLoginController extends Controller
{
    public function create(Request $request): RedirectResponse|View
    {
        if ($request->routeIs('admin.login') && $this->hasFreshAdminSession($request)) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()
            ->where('email', strtolower($credentials['email']))
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            $this->logFailedAttempt($request, $credentials['email'], 'invalid_credentials');

            return back()
                ->withErrors(['email' => 'These credentials do not match an admin account.'])
                ->onlyInput('email');
        }

        if (! $user->canAccessAdmin()) {
            $this->logFailedAttempt($request, $credentials['email'], 'unauthorized_role');

            return back()
                ->withErrors(['email' => 'This account does not have admin access.'])
                ->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('admin_authenticated_at', now()->timestamp);
        RateLimiter::clear(SecurityRateLimitKeys::middlewareKey(
            'admin-login',
            SecurityRateLimitKeys::adminLogin($request),
        ));

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    private function hasFreshAdminSession(Request $request): bool
    {
        if (! Auth::check() || ! Auth::user()->canAccessAdmin()) {
            return false;
        }

        $authenticatedAt = $request->session()->get('admin_authenticated_at');

        if (! is_numeric($authenticatedAt)) {
            return false;
        }

        $lifetime = max(1, (int) config('admin.session_lifetime_minutes', 120));

        return ! CarbonImmutable::createFromTimestamp((int) $authenticatedAt)
            ->addMinutes($lifetime)
            ->isPast();
    }

    private function logFailedAttempt(Request $request, string $email, string $reason): void
    {
        Log::warning('Admin login failed.', [
            'email_hash' => hash('sha256', mb_strtolower(trim($email))),
            'ip' => $request->ip(),
            'reason' => $reason,
        ]);
    }
}
