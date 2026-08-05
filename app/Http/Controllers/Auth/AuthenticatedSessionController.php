<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityRateLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string'],
        ]);

        $identifier = trim($credentials['identifier']);
        $phone = SecurityRateLimits::normalizePhone($identifier);

        $user = User::query()
            ->where('email', strtolower($identifier))
            ->when($phone !== '', fn ($query) => $query->orWhere('phone', $phone))
            ->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['identifier' => 'These credentials do not match our records.'])
                ->onlyInput('identifier');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        SecurityRateLimits::clearNamed(
            SecurityRateLimits::PUBLIC_LOGIN,
            SecurityRateLimits::publicLoginKey($request),
        );

        return redirect()
            ->intended(route('account.show'))
            ->with('login_success', 'Login successful.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
