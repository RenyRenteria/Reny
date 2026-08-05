<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $identifier = mb_strtolower(trim($credentials['identifier']));
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $identifier
            : User::query()
                ->where('phone', $this->normalizePhone($identifier))
                ->value('email');

        Password::sendResetLink([
            'email' => $email ?: hash('sha256', $identifier).'@invalid.local',
        ]);

        return back()->with('status', 'If the account exists, reset instructions will be sent.');
    }

    private function normalizePhone(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }
}
