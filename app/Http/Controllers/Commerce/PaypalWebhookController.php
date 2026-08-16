<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Services\Commerce\PayPalCheckoutFinalizer;
use App\Services\Commerce\PayPalSandboxE2eControl;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class PaypalWebhookController extends Controller
{
    public function handle(
        Request $request,
        RoyalPassService $royalPass,
        PayPalService $payPal,
        UserHubPurchaseSync $purchaseSync,
        PayPalCheckoutFinalizer $finalizer,
        PayPalSandboxE2eControl $e2eControl,
    ): JsonResponse {
        abort_unless($payPal->verifyWebhook($request), 401);

        return match ($request->input('event_type')) {
            'PAYMENT.CAPTURE.COMPLETED' => $e2eControl->shouldHoldCaptureWebhook()
                ? $this->holdCaptureWebhook($request)
                : $this->completeCapture($request, $finalizer),
            'PAYMENT.CAPTURE.REFUNDED' => $this->processRefund($request, $royalPass, $purchaseSync),
            default => response()->json([
                'status' => 'ignored',
                'reason' => 'event_not_subscribed',
            ]),
        };
    }

    public function refund(
        Request $request,
        RoyalPassService $royalPass,
        PayPalService $payPal,
        UserHubPurchaseSync $purchaseSync,
    ): JsonResponse {
        abort_unless($payPal->verifyWebhook($request), 401);
        abort_unless($request->input('event_type') === 'PAYMENT.CAPTURE.REFUNDED', 422);

        return $this->processRefund($request, $royalPass, $purchaseSync);
    }

    private function processRefund(
        Request $request,
        RoyalPassService $royalPass,
        UserHubPurchaseSync $purchaseSync,
    ): JsonResponse {
        $orders = $this->refundedOrders($request);

        abort_if($orders->isEmpty(), 404);

        $refundAmounts = $this->allocateRefund($orders, $this->refundAmountCents($request));
        $providerRefundId = $this->providerRefundId($request);
        $processed = 0;

        $orders->each(function (Order $order) use (&$processed, $providerRefundId, $purchaseSync, $refundAmounts, $royalPass) {
            $amount = (int) ($refundAmounts[$order->id] ?? 0);

            if ($amount <= 0) {
                return;
            }

            $refund = OrderRefund::query()->firstOrCreate([
                'order_id' => $order->id,
                'provider_refund_id' => $providerRefundId,
            ], [
                'amount_cents' => $amount,
                'currency' => $order->currency,
                'refunded_at' => now(),
            ]);

            if (! $refund->wasRecentlyCreated) {
                return;
            }

            $processed++;
            $royalPass->revokeGrant($order, (int) $order->refunds()->sum('amount_cents'));
            $purchaseSync->recordRefund($order->fresh('user'));
        });

        return response()->json([
            'status' => 'refunded',
            'refunded_orders' => $processed,
            'royal_status' => $orders->first()->user?->fresh()->accessState()->value,
        ]);
    }

    private function completeCapture(Request $request, PayPalCheckoutFinalizer $finalizer): JsonResponse
    {
        $paypalOrderId = $this->providerOrderId($request);
        $captureId = $this->completedCaptureId($request);

        if ($paypalOrderId === null || $captureId === null) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'missing_capture_reference',
            ]);
        }

        $orders = $this->capturedOrders($paypalOrderId);

        if ($orders->isEmpty()) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'order_not_found',
            ]);
        }

        if ($orders->contains(fn (Order $order): bool => ! in_array($order->status, ['pending', 'payment_review', 'completed'], true))) {
            return response()->json([
                'status' => 'ignored',
                'reason' => 'order_not_reconcilable',
            ]);
        }

        $amountCents = $this->completedAmountCents($request);
        $currency = strtoupper((string) Arr::get($request->all(), 'resource.amount.currency_code', ''));
        $expectedCurrency = strtoupper((string) $orders->pluck('currency')->unique()->sole());

        if ($amountCents === null || $amountCents !== (int) $orders->sum('amount_cents') || $currency !== $expectedCurrency) {
            Order::query()
                ->whereKey($orders->pluck('id'))
                ->whereIn('status', ['pending', 'payment_review'])
                ->update([
                    'provider_capture_id' => $captureId,
                    'status' => 'payment_review',
                    'updated_at' => now(),
                ]);

            Log::warning('PayPal capture webhook requires payment review.', $this->webhookLogContext($request, $paypalOrderId, $captureId, [
                'paypal_issue' => 'capture_amount_or_currency_mismatch',
            ]));

            return response()->json([
                'status' => 'payment_review',
                'reason' => 'capture_amount_or_currency_mismatch',
            ]);
        }

        $result = $finalizer->finalize($orders, [
            'order_id' => $paypalOrderId,
            'capture_id' => $captureId,
            'payer_id' => Arr::get($request->all(), 'resource.payer.payer_id'),
            'debug_id' => null,
            'http_status' => 200,
        ]);

        Log::info('PayPal capture webhook reconciled checkout.', $this->webhookLogContext($request, $paypalOrderId, $captureId, [
            'paypal_result' => $result['finalized'] ? 'finalized' : 'replayed',
        ]));

        return response()->json([
            'status' => $result['finalized'] ? 'completed' : 'already_completed',
            'completed_orders' => $result['finalized'] ? $result['orders']->count() : 0,
        ]);
    }

    private function holdCaptureWebhook(Request $request): JsonResponse
    {
        $paypalOrderId = $this->providerOrderId($request);
        $captureId = $this->completedCaptureId($request);
        $eventId = (string) $request->input('id', '');

        Log::warning('PayPal capture webhook held for sandbox fault verification.', [
            'paypal_capture_reference' => $captureId ? $this->reference($captureId) : null,
            'paypal_debug_id' => null,
            'paypal_endpoint' => '/paypal/webhook',
            'paypal_event_reference' => filled($eventId) ? $this->reference($eventId) : null,
            'paypal_http_status' => 503,
            'paypal_order_reference' => $paypalOrderId ? $this->reference($paypalOrderId) : null,
            'paypal_stage' => 'capture_webhook_hold',
        ]);

        return response()->json(['status' => 'retry'], 503);
    }

    /**
     * @return Collection<int, Order>
     */
    private function capturedOrders(string $providerOrderId): Collection
    {
        return Order::query()
            ->with('user')
            ->where('provider', 'paypal')
            ->where(function ($query) use ($providerOrderId) {
                $query
                    ->where('provider_order_id', $providerOrderId)
                    ->orWhereRaw('provider_order_id LIKE ? ESCAPE ?', [
                        $this->escapeLike($providerOrderId).'-%',
                        '\\',
                    ]);
            })
            ->orderBy('id')
            ->get();
    }

    private function completedCaptureId(Request $request): ?string
    {
        $value = Arr::get($request->all(), 'resource.id');

        return filled($value) ? (string) $value : null;
    }

    private function completedAmountCents(Request $request): ?int
    {
        $value = Arr::get($request->all(), 'resource.amount.value');

        return is_numeric($value) ? max(0, (int) round(((float) $value) * 100)) : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function webhookLogContext(
        Request $request,
        string $paypalOrderId,
        string $captureId,
        array $extra = [],
    ): array {
        $eventId = (string) $request->input('id', '');

        return [
            'paypal_capture_reference' => $this->reference($captureId),
            'paypal_debug_id' => null,
            'paypal_endpoint' => '/paypal/webhook',
            'paypal_event_reference' => filled($eventId) ? $this->reference($eventId) : null,
            'paypal_http_status' => 200,
            'paypal_order_reference' => $this->reference($paypalOrderId),
            'paypal_stage' => 'capture_webhook',
            ...$extra,
        ];
    }

    private function reference(string $value): string
    {
        return substr(hash('sha256', $value), 0, 16);
    }

    /**
     * Resolve the orders affected by a PayPal refund.
     *
     * Matching is always scoped to the PayPal provider so a refund can never
     * touch a local order that happens to share an identifier. The capture id
     * is the most specific reference on a refund, so when it is present and
     * matches we use it exclusively. Only when no capture matches do we fall
     * back to the order id, where the escaped prefix match is still needed to
     * cover the per-line-item rows of a single PayPal order.
     *
     * @return Collection<int, Order>
     */
    private function refundedOrders(Request $request): Collection
    {
        $captureId = $this->providerCaptureId($request);
        $providerOrderId = $this->providerOrderId($request);

        abort_unless($captureId !== null || $providerOrderId !== null, 422, 'Missing PayPal order reference.');

        if ($captureId !== null) {
            $byCapture = Order::query()
                ->withSum('refunds', 'amount_cents')
                ->where('provider', 'paypal')
                ->where('provider_capture_id', $captureId)
                ->get();

            if ($byCapture->isNotEmpty()) {
                return $byCapture;
            }
        }

        if ($providerOrderId === null) {
            return new Collection;
        }

        return Order::query()
            ->withSum('refunds', 'amount_cents')
            ->where('provider', 'paypal')
            ->where(function ($query) use ($providerOrderId) {
                $query
                    ->where('provider_order_id', $providerOrderId)
                    ->orWhereRaw('provider_order_id LIKE ? ESCAPE ?', [
                        $this->escapeLike($providerOrderId).'-%',
                        '\\',
                    ]);
            })
            ->get();
    }

    private function providerOrderId(Request $request): ?string
    {
        $value = $request->input('provider_order_id')
            ?: Arr::get($request->all(), 'resource.custom_id')
            ?: Arr::get($request->all(), 'resource.supplementary_data.related_ids.order_id');

        return filled($value) ? (string) $value : null;
    }

    private function providerCaptureId(Request $request): ?string
    {
        $value = $request->input('provider_capture_id')
            ?: Arr::get($request->all(), 'resource.supplementary_data.related_ids.capture_id')
            ?: $this->captureIdFromLinks(Arr::get($request->all(), 'resource.links', []));

        return filled($value) ? (string) $value : null;
    }

    private function providerRefundId(Request $request): string
    {
        $value = $request->input('provider_refund_id') ?: Arr::get($request->all(), 'resource.id');

        if (filled($value)) {
            return (string) $value;
        }

        return 'legacy-'.hash('sha256', (string) json_encode($request->all(), JSON_UNESCAPED_SLASHES));
    }

    private function refundAmountCents(Request $request): ?int
    {
        $value = Arr::get($request->all(), 'resource.amount.value');

        if (! is_numeric($value)) {
            return null;
        }

        return max(0, (int) round(((float) $value) * 100));
    }

    /**
     * @return array<int, int>
     */
    private function allocateRefund(Collection $orders, ?int $refundAmountCents): array
    {
        $remainingByOrder = $orders->mapWithKeys(fn (Order $order): array => [
            $order->id => max(0, (int) $order->amount_cents - (int) ($order->refunds_sum_amount_cents ?? 0)),
        ]);
        $total = (int) $remainingByOrder->sum();

        if ($total === 0) {
            return $remainingByOrder->all();
        }

        $refundTotal = min($refundAmountCents ?? $total, $total);
        $remaining = $refundTotal;
        $lastId = $orders->last()?->id;

        return $orders->mapWithKeys(function (Order $order) use (&$remaining, $lastId, $refundTotal, $remainingByOrder, $total): array {
            $capacity = (int) $remainingByOrder->get($order->id, 0);
            $amount = $order->id === $lastId
                ? min($remaining, $capacity)
                : min($remaining, $capacity, (int) round(($capacity / $total) * $refundTotal));
            $remaining -= $amount;

            return [$order->id => $amount];
        })->all();
    }

    /**
     * PayPal refund events reference the original capture through a link with
     * rel "up" pointing at /v2/payments/captures/{capture_id}.
     *
     * @param  mixed  $links
     */
    private function captureIdFromLinks($links): ?string
    {
        if (! is_array($links)) {
            return null;
        }

        foreach ($links as $link) {
            if (! is_array($link) || ($link['rel'] ?? null) !== 'up') {
                continue;
            }

            $href = (string) ($link['href'] ?? '');

            if (preg_match('#/captures/([^/?]+)#', $href, $matches) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Escape LIKE wildcards so an attacker-influenced reference cannot widen
     * the prefix match (e.g. a "%" turning into match-everything).
     */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
