<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
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

        $orders->each(function (Order $order) use ($purchaseSync, $royalPass) {
            $royalPass->revokeGrant($order);
            $purchaseSync->recordRefund($order->fresh('user'));
        });

        return response()->json([
            'status' => 'refunded',
            'refunded_orders' => $orders->count(),
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
