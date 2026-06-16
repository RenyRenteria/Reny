<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\AccessStatePresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(Request $request): View
    {
        return view('auth.register', [
            'sourceRoute' => AccessStatePresenter::pathFromUrl($request->session()->get('url.intended')),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->merge([
            'username' => Str::lower(ltrim(trim((string) $request->input('username')), '@')),
            'country_code' => Str::upper(trim((string) $request->input('country_code'))),
        ]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'min:3',
                'max:24',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique(User::class, 'username'),
            ],
            'identifier' => ['required', 'string', 'max:255'],
            'country_code' => ['required', 'string', 'size:2', 'regex:/^[A-Z]{2}$/'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $identifier = trim($validated['identifier']);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? strtolower($identifier) : null;
        $phone = $email ? null : $this->normalizePhone($identifier);

        if (! $email && $phone === '') {
            return back()
                ->withErrors(['identifier' => 'Use a valid email or phone number.'])
                ->onlyInput('identifier', 'name', 'username', 'country_code');
        }

        if ($email && User::where('email', $email)->exists()) {
            return back()
                ->withErrors(['identifier' => 'This email is already registered.'])
                ->onlyInput('identifier', 'name', 'username', 'country_code');
        }

        if ($phone && User::where('phone', $phone)->exists()) {
            return back()
                ->withErrors(['identifier' => 'This phone number is already registered.'])
                ->onlyInput('identifier', 'name', 'username', 'country_code');
        }

        $user = User::create([
            'name' => trim($validated['name']),
            'username' => $validated['username'],
            'email' => $email ?: "phone-{$phone}@renyrenteria.local",
            'phone' => $phone ?: null,
            'country_code' => $validated['country_code'],
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
