<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(Request $request): RedirectResponse|View
    {
        if ($request->session()->get('reny_admin_authenticated') === true) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'password' => ['required', 'string'],
        ]);

        $password = config('admin.password');

        if (! is_string($password) || $password === '') {
            return back()
                ->withErrors(['password' => 'Admin password is not configured.'])
                ->onlyInput();
        }

        if (! hash_equals($password, $validated['password'])) {
            return back()
                ->withErrors(['password' => 'Invalid admin password.'])
                ->onlyInput();
        }

        $request->session()->regenerate();
        $request->session()->put('reny_admin_authenticated', true);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $request->session()->forget('reny_admin_authenticated');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
