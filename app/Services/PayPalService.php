<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PayPalService
{
    /**
     * @param  array<int, array{name: string, unit_amount_cents: int, quantity: int}>  $items
     * @return array{order_id: string, payload: array<string, mixed>}
     */
    public function createOrder(int $expectedAmountCents, string $currency, array $items = []): array
    {
        $endpoint = '/v2/checkout/orders';
        $failureMessage = 'We could not start PayPal checkout. No charge was made. Try again.';
        $currency = strtoupper($currency);
        $purchaseUnit = [
            'amount' => [
                'currency_code' => $currency,
                'value' => $this->centsToDecimal($expectedAmountCents),
            ],
        ];

        if ($items !== []) {
            $purchaseUnit['amount']['breakdown'] = [
                'item_total' => [
                    'currency_code' => $currency,
                    'value' => $this->centsToDecimal($expectedAmountCents),
                ],
            ];
            $purchaseUnit['items'] = collect($items)
                ->map(fn (array $item): array => [
                    'name' => Str::limit($item['name'], 127, ''),
                    'quantity' => (string) max(1, $item['quantity']),
                    'unit_amount' => [
                        'currency_code' => $currency,
                        'value' => $this->centsToDecimal($item['unit_amount_cents']),
                    ],
                ])
                ->values()
                ->all();
        }

        $response = $this->request(
            stage: 'create_order',
            endpoint: $endpoint,
            failureMessage: $failureMessage,
            callback: fn (): Response => $this->paypal()
                ->withToken($this->accessToken('create_order', $failureMessage))
                ->withHeaders(['PayPal-Request-Id' => 'create-'.Str::uuid()])
                ->post($this->url($endpoint), [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [$purchaseUnit],
                ]),
        );

        $this->ensureOk($response, $failureMessage, 'create_order', $endpoint);

        $payload = $response->json();

        if (! is_array($payload) || blank(Arr::get($payload, 'id'))) {
            $this->logUnexpectedResponse($response, 'create_order', $endpoint, 'missing_order_id');

            throw $this->providerFailure($failureMessage);
        }

        $orderId = (string) Arr::get($payload, 'id');
        $this->logCompletedRequest($response, 'create_order', $endpoint, [
            'paypal_order_reference' => $this->reference($orderId),
        ]);

        return [
            'order_id' => $orderId,
            'payload' => $payload,
        ];
    }

    /**
     * @return array{order_id: string, capture_id: string, payer_id: string|null, payload: array<string, mixed>, debug_id: string|null, http_status: int}
     */
    public function captureOrder(string $paypalOrderId, int $expectedAmountCents, string $currency): array
    {
        $endpoint = '/v2/checkout/orders/{order_id}/capture';
        $requestEndpoint = "/v2/checkout/orders/{$paypalOrderId}/capture";
        $failureMessage = 'PayPal approved the payment, but confirmation is pending. Do not retry; contact support with the time and amount.';
        $references = [
            'paypal_order_reference' => $this->reference($paypalOrderId),
        ];
        $response = $this->request(
            stage: 'capture_order',
            endpoint: $endpoint,
            failureMessage: $failureMessage,
            callback: fn (): Response => $this->paypal()
                ->withToken($this->accessToken('capture_order', $failureMessage))
                ->withHeaders(['PayPal-Request-Id' => "capture-{$paypalOrderId}"])
                ->post($this->url($requestEndpoint)),
            context: $references,
        );

        $this->ensureOk($response, $failureMessage, 'capture_order', $endpoint, $references);

        $payload = $response->json();

        if (! is_array($payload) || Arr::get($payload, 'status') !== 'COMPLETED') {
            $this->logUnexpectedResponse($response, 'capture_order', $endpoint, 'capture_not_completed', $references);

            throw ValidationException::withMessages([
                'paypal_order_id' => $failureMessage,
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
            $this->logUnexpectedResponse($response, 'capture_order', $endpoint, 'capture_amount_mismatch', $references);

            throw ValidationException::withMessages([
                'paypal_order_id' => $failureMessage,
            ]);
        }

        $captureId = $completedCaptures->pluck('id')->filter()->first();

        if (blank($captureId)) {
            $this->logUnexpectedResponse($response, 'capture_order', $endpoint, 'missing_capture_id', $references);

            throw ValidationException::withMessages([
                'paypal_order_id' => $failureMessage,
            ]);
        }

        $references['paypal_capture_reference'] = $this->reference((string) $captureId);
        $this->logCompletedRequest($response, 'capture_order', $endpoint, $references);

        return [
            'order_id' => (string) Arr::get($payload, 'id', $paypalOrderId),
            'capture_id' => (string) $captureId,
            'payer_id' => Arr::get($payload, 'payer.payer_id'),
            'payload' => $payload,
            'debug_id' => $response->header('PayPal-Debug-Id'),
            'http_status' => $response->status(),
        ];
    }

    public function suspendSubscription(string $subscriptionId, string $reason): void
    {
        $response = $this->paypal()
            ->withToken($this->accessToken('subscription_suspend', 'PayPal subscription pause failed.'))
            ->post($this->url("/v1/billing/subscriptions/{$subscriptionId}/suspend"), [
                'reason' => Str::limit($reason, 128, ''),
            ]);

        $this->ensureOk(
            $response,
            'PayPal subscription pause failed.',
            'subscription_suspend',
            '/v1/billing/subscriptions/{subscription_id}/suspend',
        );
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

        $endpoint = '/v1/notifications/verify-webhook-signature';
        $failureMessage = 'PayPal webhook verification failed.';
        $eventId = (string) $request->input('id', '');
        $context = filled($eventId)
            ? ['paypal_event_reference' => $this->reference($eventId)]
            : [];
        $response = $this->request(
            stage: 'webhook_verification',
            endpoint: $endpoint,
            failureMessage: $failureMessage,
            callback: fn (): Response => $this->paypal()
                ->withToken($this->accessToken('webhook_verification', $failureMessage))
                ->post($this->url($endpoint), [
                    ...$headers,
                    'webhook_id' => $webhookId,
                    'webhook_event' => $request->all(),
                ]),
            context: $context,
        );

        $this->ensureOk($response, $failureMessage, 'webhook_verification', $endpoint, $context);

        if ($response->json('verification_status') !== 'SUCCESS') {
            $this->logUnexpectedResponse($response, 'webhook_verification', $endpoint, 'signature_not_verified', $context);

            return false;
        }

        $this->logCompletedRequest($response, 'webhook_verification', $endpoint, $context);

        return true;
    }

    private function accessToken(string $stage, string $failureMessage): string
    {
        $clientId = config('services.paypal.client_id');
        $clientSecret = config('services.paypal.client_secret');

        if (! $clientId || ! $clientSecret) {
            Log::critical('PayPal credentials are not configured.', [
                'paypal_stage' => $stage,
                'paypal_endpoint' => '/v1/oauth2/token',
            ]);

            throw $this->providerFailure($failureMessage, 503);
        }

        $endpoint = '/v1/oauth2/token';
        $response = $this->request(
            stage: "{$stage}.authentication",
            endpoint: $endpoint,
            failureMessage: $failureMessage,
            callback: fn (): Response => $this->paypal()
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->post($this->url($endpoint), [
                    'grant_type' => 'client_credentials',
                ]),
        );

        $this->ensureOk($response, $failureMessage, "{$stage}.authentication", $endpoint);

        return (string) $response->json('access_token');
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function ensureOk(Response $response, string $message, string $stage, string $endpoint, array $context = []): void
    {
        if ($response->successful()) {
            return;
        }

        Log::warning('PayPal API request failed.', [
            'paypal_debug_id' => $response->header('PayPal-Debug-Id'),
            'paypal_endpoint' => $endpoint,
            'paypal_error_code' => $response->json('name'),
            'paypal_error_issue' => $response->json('details.0.issue'),
            'paypal_http_status' => $response->status(),
            'paypal_stage' => $stage,
            ...$context,
        ]);

        throw $this->providerFailure($message);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function request(string $stage, string $endpoint, string $failureMessage, callable $callback, array $context = []): Response
    {
        try {
            return $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error('PayPal API request did not complete.', [
                'exception' => $exception::class,
                'paypal_endpoint' => $endpoint,
                'paypal_stage' => $stage,
                ...$context,
            ]);

            throw $this->providerFailure($failureMessage);
        }
    }

    private function providerFailure(string $message, int $status = 502): ValidationException
    {
        return ValidationException::withMessages([
            'paypal' => $message,
        ])->status($status);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function logUnexpectedResponse(Response $response, string $stage, string $endpoint, string $issue, array $context = []): void
    {
        Log::warning('PayPal API returned an unexpected checkout response.', [
            'paypal_debug_id' => $response->header('PayPal-Debug-Id'),
            'paypal_endpoint' => $endpoint,
            'paypal_http_status' => $response->status(),
            'paypal_issue' => $issue,
            'paypal_provider_status' => $response->json('status'),
            'paypal_stage' => $stage,
            ...$context,
        ]);
    }

    /**
     * @param  array<string, string|null>  $context
     */
    private function logCompletedRequest(Response $response, string $stage, string $endpoint, array $context = []): void
    {
        Log::info('PayPal API request completed.', [
            'paypal_debug_id' => $response->header('PayPal-Debug-Id'),
            'paypal_endpoint' => $endpoint,
            'paypal_http_status' => $response->status(),
            'paypal_stage' => $stage,
            ...$context,
        ]);
    }

    private function reference(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
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
