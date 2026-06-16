<?php

namespace App\Services\Media;

class MuxWebhookVerifier
{
    public function verify(string $payload, ?string $signatureHeader): bool
    {
        $secret = config('services.mux.webhook_secret');

        if (blank($secret) || blank($signatureHeader)) {
            return false;
        }

        $parts = collect(explode(',', $signatureHeader))
            ->mapWithKeys(function (string $part): array {
                [$key, $value] = array_pad(explode('=', trim($part), 2), 2, null);

                return $key === null || $value === null ? [] : [$key => $value];
            });

        $timestamp = $parts->get('t');
        $signature = $parts->get('v1');

        if (blank($timestamp) || blank($signature) || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        $toleranceSeconds = (int) config('services.mux.webhook_tolerance_seconds', 300);

        if (abs(now()->timestamp - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = hash_hmac('sha256', "{$timestamp}.{$payload}", (string) $secret);

        return hash_equals($expected, (string) $signature);
    }
}
