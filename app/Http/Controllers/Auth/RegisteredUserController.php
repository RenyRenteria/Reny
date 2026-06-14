<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'identifier' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $identifier = trim($validated['identifier']);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : null;
        $phone = $email ? null : $this->normalizePhone($identifier);

        if (! $email && $phone === '') {
            return back()
                ->withErrors(['identifier' => 'Use a valid email or phone number.'])
                ->onlyInput('identifier', 'name');
        }

        if ($email && User::where('email', $email)->exists()) {
            return back()
                ->withErrors(['identifier' => 'This email is already registered.'])
                ->onlyInput('identifier', 'name');
        }

        if ($phone !== '' && User::where('phone', $phone)->exists()) {
            return back()
                ->withErrors(['identifier' => 'This phone number is already registered.'])
                ->onlyInput('identifier', 'name');
        }

        $user = User::create([
            'name' => $validated['name'] ?: 'Royal Member',
            'email' => $email ?: "phone-{$phone}@renyrenteria.local",
            'phone' => $phone ?: null,
            'password' => $validated['password'],
            'role' => 'fan',
            'royal_status' => 'open',
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('account.show');
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
