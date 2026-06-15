<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class PayPalService
{
    /**
     * @return array{order_id: string, payload: array<string, mixed>}
     */
    public function createOrder(int $expectedAmountCents, string $currency): array
    {
        $response = $this->paypal()
            ->withToken($this->accessToken())
            ->withHeaders(['PayPal-Request-Id' => 'create-'.Str::uuid()])
            ->post($this->url('/v2/checkout/orders'), [
                'intent' => 'CAPTURE',
                'purchase_units' => [
                    [
                        'amount' => [
                            'currency_code' => strtoupper($currency),
                            'value' => $this->centsToDecimal($expectedAmountCents),
                        ],
                    ],
                ],
            ]);

        $this->ensureOk($response, 'PayPal order creation failed.');

        $payload = $response->json();

        if (! is_array($payload) || blank(Arr::get($payload, 'id'))) {
            throw ValidationException::withMessages([
                'paypal' => 'PayPal did not return an order id.',
            ]);
        }

        return [
            'order_id' => (string) Arr::get($payload, 'id'),
            'payload' => $payload,
        ];
    }

    /**
     * @return array{order_id: string, capture_id: string|null, payer_id: string|null, payload: array<string, mixed>}
     */
    public function captureOrder(string $paypalOrderId, int $expectedAmountCents, string $currency): array
    {
        $response = $this->paypal()
            ->withToken($this->accessToken())
            ->withHeaders(['PayPal-Request-Id' => "capture-{$paypalOrderId}"])
            ->post($this->url("/v2/checkout/orders/{$paypalOrderId}/capture"));

        $this->ensureOk($response, 'PayPal capture failed.');

        $payload = $response->json();

        if (! is_array($payload) || Arr::get($payload, 'status') !== 'COMPLETED') {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'PayPal order was not completed.',
            ]);
        }

        $captures = collect(Arr::get($payload, 'purchase_units', []))
            ->flatMap(fn (array $unit) => Arr::get($unit, 'payments.captures', []));

        $completedCaptures = $captures->filter(fn (array $capture) => Arr::get($capture, 'status') === 'COMPLETED');

        $capturedAmount = $completedCaptures->sum(function (array $capture) use ($currency) {
            if (strtoupper((string) Arr::get($capture, 'amount.currency_code')) !== strtoupper($currency)) {
                return 0;
            }

            return $this->decimalToCents((string) Arr::get($capture, 'amount.value', '0'));
        });

        if ($capturedAmount !== $expectedAmountCents) {
            throw ValidationException::withMessages([
                'paypal_order_id' => 'PayPal capture amount does not match the checkout total.',
            ]);
        }

        return [
            'order_id' => (string) Arr::get($payload, 'id', $paypalOrderId),
            'capture_id' => $completedCaptures->pluck('id')->filter()->first(),
            'payer_id' => Arr::get($payload, 'payer.payer_id'),
            'payload' => $payload,
        ];
    }

    public function verifyWebhook(Request $request): bool
    {
        $webhookId = config('services.paypal.webhook_id');

        if (! $webhookId) {
            return false;
        }

        $headers = [
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
        ];

        if (collect($headers)->contains(fn ($value) => blank($value))) {
            return false;
        }

        $response = $this->paypal()
            ->withToken($this->accessToken())
            ->post($this->url('/v1/notifications/verify-webhook-signature'), [
                ...$headers,
                'webhook_id' => $webhookId,
                'webhook_event' => $request->all(),
            ]);

        return $response->json('verification_status') === 'SUCCESS';
    }

    private function accessToken(): string
    {
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.client_secret');

        if (! $clientId || ! $clientSecret) {
            throw ValidationException::withMessages([
                'paypal' => 'PayPal credentials are not configured.',
            ]);
        }

        $response = $this->paypal()
            ->withBasicAuth($clientId, $clientSecret)
            ->asForm()
            ->post($this->url('/v1/oauth2/token'), [
                'grant_type' => 'client_credentials',
            ]);

        $this->ensureOk($response, 'PayPal authentication failed.');

        return (string) $response->json('access_token');
    }

    private function ensureOk(Response $response, string $message): void
    {
        if ($response->successful()) {
            return;
        }

        throw ValidationException::withMessages([
            'paypal' => $message,
        ]);
    }

    private function paypal()
    {
        return Http::acceptJson()->timeout(10);
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.paypal.base_url'), '/').$path;
    }

    private function decimalToCents(string $value): int
    {
        return (int) round(((float) $value) * 100);
    }

    private function centsToDecimal(int $value): string
    {
        return number_format($value / 100, 2, '.', '');
    }
}
