<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\View\View;

class PasswordResetLinkController extends Controller
{
    private const STATUS_MESSAGE = 'If this account has email recovery enabled, reset instructions will be sent.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'identifier' => ['required', 'string', 'max:255'],
        ]);

        $email = $this->recoveryEmail($request->string('identifier')->toString());

        if ($email !== null) {
            PasswordBroker::sendResetLink(['email' => $email]);
        }

        return back()->with('status', self::STATUS_MESSAGE);
    }

    private function recoveryEmail(string $identifier): ?string
    {
        $identifier = trim($identifier);

        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            return strtolower($identifier);
        }

        $phone = preg_replace('/\D+/', '', $identifier) ?? '';

        if ($phone === '') {
            return null;
        }

        return User::query()
            ->where('phone', $phone)
            ->value('email');
    }
}
