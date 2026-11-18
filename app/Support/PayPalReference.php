<?php

namespace App\Support;

use RuntimeException;

class PayPalReference
{
    public static function hash(string $value, ?string $key = null): string
    {
        if ($key === null) {
            $e2eKey = (string) config('services.paypal.e2e.reference_key');
            $key = (bool) config('services.paypal.e2e.enabled') && $e2eKey !== ''
                ? $e2eKey
                : (string) config('app.key');
        }

        if ($key === '') {
            throw new RuntimeException('A secret key is required to correlate PayPal references.');
        }

        return substr(hash_hmac('sha256', $value, $key), 0, 16);
    }
}
