<?php

namespace App\Services\Yoycol;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class YoycolClient
{
    public function request(string $method, string $endpoint, array $data = []): mixed
    {
        $access = (string) config('yoycol.access_key');
        $secret = (string) config('yoycol.secret_key');
        if (! config('yoycol.enabled') || $access === '' || $secret === '') {
            throw ValidationException::withMessages(['yoycol' => 'Yoycol is not configured.'])->status(503);
        }

        $path = '/api/2025/open/v4/'.$endpoint;
        $timestamp = (string) now()->getTimestampMs();
        $nonce = bin2hex(random_bytes(16));
        $signature = "method={$method}\npath={$path}\ntimestamp={$timestamp}\nnonce={$nonce}\naccessKey={$access}\nalgorithm=HmacSHA256\nversion=4.0";
        if ($method === 'GET' && $data !== []) {
            ksort($data, SORT_STRING);
            $params = [];
            foreach ($data as $key => $value) {
                $params[] = $key.'='.$value;
            }
            $signature .= "\nparams=".implode('&', $params);
        }

        try {
            // Never automatically retry a write: the provider does not document idempotency.
            $response = Http::acceptJson()->asJson()->connectTimeout(5)->timeout(25)
                ->withoutRedirecting()->withHeaders([
                    'X-API-Access-Key' => $access, 'X-API-Timestamp' => $timestamp,
                    'X-API-Nonce' => $nonce, 'X-API-Algorithm' => 'HmacSHA256',
                    'X-API-Version' => '4.0',
                    'X-API-Signature' => base64_encode(hash_hmac('sha256', $signature, $secret, true)),
                ])->send($method, 'https://www.yoycol.com'.$path, [$method === 'GET' ? 'query' : 'json' => $data]);
        } catch (Throwable) {
            throw ValidationException::withMessages(['yoycol' => 'Yoycol did not respond. Please try again later.'])->status(503);
        }

        if (! $response->successful() || (string) $response->json('code') !== '100000') {
            Log::warning('Yoycol request failed.', ['method' => $method, 'endpoint' => $endpoint, 'http_status' => $response->status()]);
            throw ValidationException::withMessages(['yoycol' => 'Yoycol could not complete this request.'])->status(502);
        }

        return $response->json('data');
    }
}
