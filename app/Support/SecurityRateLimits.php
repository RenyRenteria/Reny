<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

final class SecurityRateLimits
{
    private const CHECKOUT_GUEST_IDENTITY_HASH_SESSION_KEY = 'security.checkout.guest_identity_hash';

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

        $submittedIdentifier = (string) ($request->input('identifier')
            ?: $request->input('customer_email')
            ?: $request->input('customer_phone'));

        if ($submittedIdentifier !== '') {
            $identityHash = self::identityHash('identifier:'.self::normalizeIdentifier($submittedIdentifier));

            if ($request->hasSession()) {
                // Cancel only submits its PayPal order id. Keep the opaque guest
                // identity in the signed session so every checkout mutation uses
                // the same budget without trusting an extra cancel field.
                $request->session()->put(self::CHECKOUT_GUEST_IDENTITY_HASH_SESSION_KEY, $identityHash);
            }

            return self::scopeIdentityHash($identityHash, $request);
        }

        $sessionIdentityHash = $request->hasSession()
            ? $request->session()->get(self::CHECKOUT_GUEST_IDENTITY_HASH_SESSION_KEY)
            : null;

        $identityHash = is_string($sessionIdentityHash) && preg_match('/\A[0-9a-f]{64}\z/', $sessionIdentityHash) === 1
            ? $sessionIdentityHash
            : self::identityHash('identifier:');

        return self::scopeIdentityHash($identityHash, $request);
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
        return self::scopeIdentityHash(self::identityHash($identity), $request);
    }

    private static function identityHash(string $identity): string
    {
        return hash('sha256', $identity !== '' ? $identity : 'anonymous');
    }

    private static function scopeIdentityHash(string $identityHash, Request $request): string
    {
        return $identityHash.'|'.($request->ip() ?: 'anonymous');
    }
}
