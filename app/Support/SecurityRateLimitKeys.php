<?php

namespace App\Support;

use Illuminate\Http\Request;

final class SecurityRateLimitKeys
{
    public static function publicLogin(Request $request): string
    {
        return self::key('public-login', $request->input('identifier'), $request->ip());
    }

    public static function adminLogin(Request $request): string
    {
        return self::key('admin-login', $request->input('email'), $request->ip());
    }

    public static function passwordReset(Request $request): string
    {
        return self::key('password-reset', $request->input('identifier'), $request->ip());
    }

    public static function checkout(Request $request): string
    {
        return self::key('checkout', $request->user()?->getAuthIdentifier(), $request->ip());
    }

    public static function middlewareKey(string $limiter, string $key): string
    {
        return md5($limiter.$key);
    }

    private static function key(string $scope, mixed $identifier, ?string $ip): string
    {
        $normalizedIdentifier = mb_strtolower(trim((string) $identifier));

        return hash('sha256', implode('|', [
            $scope,
            $normalizedIdentifier,
            $ip ?: 'unknown',
        ]));
    }
}
