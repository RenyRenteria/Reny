<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\SecurityRateLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = SecurityRateLimits::normalizeIdentifier($validated['identifier']);
        $user = User::query()
            ->when(
                str_contains($identifier, '@'),
                fn ($query) => $query->where('email', $identifier),
                fn ($query) => $query->where('phone', $identifier),
            )
            ->first();

        if ($user && ! Str::endsWith(Str::lower($user->email), '@renyrenteria.local')) {
            Password::sendResetLink(['email' => $user->email]);
        }

        return back()->with('status', 'If recovery is available for this account, reset instructions will be sent.');
    }
}
