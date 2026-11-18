<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Services\PayPalService;
use App\Support\PayPalReference;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayPalPaymentRecovery
{
    public function __construct(
        private readonly PayPalService $paypal,
        private readonly PayPalCheckoutFinalizer $finalizer,
    ) {}

    public function recover(Order $order): void
    {
        $order->refresh();
        $reference = data_get($order->metadata, 'checkout.paypal_order_id');
        if ($order->provider !== 'paypal' || ! is_string($reference) || $reference === ''
            || ! in_array($order->status, ['pending', 'payment_review'], true)
            || ! data_get($order->metadata, 'checkout.capture_started')) {
            return;
        }

        Cache::lock('paypal-recovery-'.hash('sha256', $reference), 120)->get(function () use ($reference, $order): void {
            try {
                // Resolve the entire checkout, including repeated line items, before validating its total.
                $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $reference);
                $orders = Order::where('provider', 'paypal')
                    ->whereRaw('provider_order_id LIKE ? ESCAPE ?', [$escaped.'-%', '\\'])
                    ->orderBy('id')->get();
                if (! $orders->contains('id', $order->id) || $orders->isEmpty()
                    || $orders->pluck('currency')->unique()->all() !== ['USD']
                    || $orders->contains(fn (Order $row) => ! in_array($row->status, ['pending', 'payment_review'], true)
                        || $row->refund_amount_cents > 0 || $row->refunds()->exists())) {
                    return;
                }

                // Never repeat a capture. A GET can recover an already completed payment.
                $capture = $this->paypal->confirmedOrder($reference, (int) $orders->sum('amount_cents'), 'USD');
                if (! $capture || $capture['order_id'] !== $reference) {
                    return;
                }
                $this->finalizer->finalize($orders, $capture);
                foreach ($orders as $row) {
                    $row->yoycolFulfillment?->update(['last_error' => null]);
                }
            } catch (Throwable $exception) {
                $order->yoycolFulfillment?->update(['last_error' => 'Customer payment confirmation needs review. Do not charge the customer again.']);
                Log::warning('PayPal payment recovery needs review.', [
                    'paypal_order_reference' => PayPalReference::hash($reference),
                    'exception' => $exception::class,
                ]);
            }
        });
    }
}
