<?php

namespace App\Services\Yoycol;

use App\Models\Order;
use App\Services\PayPalService;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Support\Facades\DB;
use Throwable;

class YoycolPaymentRecovery
{
    public function __construct(
        private readonly PayPalService $paypal,
        private readonly RoyalPassService $royalPass,
        private readonly UserHubPurchaseSync $purchaseSync,
    ) {}

    public function recover(Order $order): void
    {
        $reference = data_get($order->metadata, 'checkout.paypal_order_id');
        if (! is_string($reference) || ! data_get($order->metadata, 'checkout.capture_started') || ! $order->yoycolFulfillment) {
            return;
        }
        try {
            // Read only: never issue another capture while recovering a lost response.
            $capture = $this->paypal->confirmedOrder($reference, $order->amount_cents, $order->currency);
            if (! $capture || $capture['order_id'] !== $reference) {
                return;
            }
            DB::transaction(function () use ($order, $capture): void {
                $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
                if (! in_array($order->status, ['pending', 'payment_review'], true) || $order->refund_amount_cents > 0
                    || Order::where('provider_capture_id', $capture['capture_id'])->where('id', '!=', $order->id)->exists()) {
                    return;
                }
                $order->update(['status' => 'completed', 'completed_at' => now(), 'provider_capture_id' => $capture['capture_id']]);
                $user = $order->user()->lockForUpdate()->firstOrFail();
                $this->royalPass->log($user, 'purchase', 'order', $order->provider_order_id, [
                    'product_key' => $order->product_key, 'provider' => 'paypal', 'paypal_capture_id' => $capture['capture_id'],
                ]);
                if (! $order->royal_granted_until) {
                    $this->royalPass->grantMonth($user, $order);
                }
                $this->purchaseSync->recordCompletedOrder($user, $order, $capture);
                $order->yoycolFulfillment->update(['last_error' => null]);
            });
        } catch (Throwable) {
            $order->yoycolFulfillment->update(['last_error' => 'Customer payment confirmation needs review. Do not charge the customer again.']);
        }
    }
}
