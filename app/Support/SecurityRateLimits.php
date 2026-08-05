<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class SecurityRateLimits
{
    public const PUBLIC_LOGIN = 'auth-login';

    public const ADMIN_LOGIN = 'admin-auth-login';

    public const PASSWORD_RESET = 'auth-password-reset';

    public const CHECKOUT = 'checkout';

    public static function publicLoginKey(Request $request): string
    {
        return self::scopeKey(
            'identifier:'.self::normalizeIdentifier((string) $request->input('identifier')),
            $request,
        );
    }

    public static function adminLoginKey(Request $request): string
    {
        return self::scopeKey(
            'email:'.self::normalizeIdentifier((string) $request->input('email')),
            $request,
        );
    }

    public static function passwordResetKey(Request $request): string
    {
        return self::scopeKey(
            'identifier:'.self::normalizeIdentifier((string) $request->input('identifier')),
            $request,
        );
    }

    public static function checkoutKey(Request $request): string
    {
        if ($request->user()) {
            return self::scopeKey('user:'.$request->user()->getAuthIdentifier(), $request);
        }

        return self::scopeKey('guest', $request);
    }

    public static function normalizeIdentifier(string $identifier): string
    {
        $identifier = trim($identifier);

        if (str_contains($identifier, '@')) {
            return Str::lower($identifier);
        }

        return self::normalizePhone($identifier);
    }

    public static function normalizePhone(string $phone): string
    {
        return preg_replace('/\D+/', '', $phone) ?? '';
    }

    public static function clearNamed(string $limiterName, string $key): void
    {
        // Laravel hashes named middleware keys as md5($limiterName.$limitKey).
        RateLimiter::clear(md5($limiterName.$key));
    }

    private static function scopeKey(string $identity, Request $request): string
    {
        $identityHash = hash('sha256', $identity !== '' ? $identity : 'anonymous');

        return $identityHash.'|'.($request->ip() ?: 'anonymous');
    }
}
