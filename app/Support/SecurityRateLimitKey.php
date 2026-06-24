<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SecurityRateLimitKey
{
    public static function auth(Request $request): string
    {
        return self::fromValue('auth', (string) $request->input('identifier', ''), $request);
    }

    public static function adminAuth(Request $request): string
    {
        return self::fromValue('admin-auth', (string) $request->input('email', ''), $request);
    }

    public static function checkout(Request $request): string
    {
        $identifier = $request->input('identifier')
            ?? $request->input('customer_email')
            ?? $request->input('paypal_order_id')
            ?? $request->input('local_reference')
            ?? '';

        return self::fromValue('checkout', (string) $identifier, $request);
    }

    public static function community(Request $request): string
    {
        return self::fromValue('community', (string) ($request->user()?->getAuthIdentifier() ?? ''), $request);
    }

    public static function namedLimiter(string $limiterName, string $key): string
    {
        return md5($limiterName.$key);
    }

    private static function fromValue(string $prefix, string $value, Request $request): string
    {
        $identifier = Str::lower(Str::squish($value)) ?: 'anonymous';
        $ip = $request->ip() ?: 'anonymous';

        return $prefix.'|'.hash('sha256', $identifier.'|'.$ip);
    }
}
