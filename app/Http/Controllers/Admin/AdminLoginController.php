<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
            return back()
                ->withErrors(['email' => 'These credentials do not match an admin account.'])
                ->onlyInput('email');
        }

        if (! $user->canAccessAdmin()) {
            return back()
                ->withErrors(['email' => 'This account does not have admin access.'])
                ->onlyInput('email');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('admin_authenticated_at', now()->timestamp);

        return redirect()->route('admin.dashboard');
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
}
