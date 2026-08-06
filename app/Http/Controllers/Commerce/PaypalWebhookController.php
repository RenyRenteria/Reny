<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class PaypalWebhookController extends Controller
{
    public function refund(
        Request $request,
        RoyalPassService $royalPass,
        PayPalService $payPal,
        UserHubPurchaseSync $purchaseSync,
    ): JsonResponse {
        abort_unless($payPal->verifyWebhook($request), 401);
        abort_unless($request->input('event_type') === 'PAYMENT.CAPTURE.REFUNDED', 422);

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
