<?php

namespace App\Services\Yoycol;

use App\Models\YoycolFulfillment;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Throwable;

class YoycolFulfillmentService
{
    public function __construct(private readonly YoycolClient $client) {}

    public function process(YoycolFulfillment $fulfillment): void
    {
        if (! config('yoycol.enabled')) {
            return;
        }
        Cache::lock('yoycol-fulfillment-'.$fulfillment->id, 600)->get(function () use ($fulfillment): void {
            $fulfillment->refresh()->load('order');
            if ($fulfillment->state === 'cancelled') {
                return;
            }
            if ($fulfillment->order->status !== 'completed' || ! $fulfillment->order->provider_capture_id) {
                if ($fulfillment->order->status === 'refunded' || $fulfillment->order->refund_amount_cents > 0) {
                    $fulfillment->update(['state' => 'refund_review', 'last_error' => 'Review the customer refund and cancel the supplier order before production.']);
                }

                return;
            }
            if ($fulfillment->order->refund_amount_cents > 0 || $fulfillment->state === 'refund_review') {
                $fulfillment->update(['state' => 'refund_review']);

                return;
            }

            try {
                if (! $fulfillment->provider_order_id) {
                    if ($fulfillment->attempted_at) {
                        // A crash/timeout may have happened after Yoycol accepted the write.
                        $found = $this->findRemoteOrder($fulfillment->store_order_sn);
                        if (! $found) {
                            $fulfillment->update(['state' => 'review', 'last_error' => 'Order submission needs review. No automatic resubmission.']);

                            return;
                        }
                        $this->saveSummary($fulfillment, $found);
                    } else {
                        // Persist BEFORE calling the provider. A second worker must reconcile.
                        $claimed = YoycolFulfillment::whereKey($fulfillment->id)->whereNull('attempted_at')
                            ->update(['attempted_at' => now(), 'state' => 'submitting']);
                        if (! $claimed) {
                            return;
                        }
                        $summary = $this->client->request('POST', 'orders', $fulfillment->request_payload);
                        $this->saveSummary($fulfillment, $summary);
                    }
                }
                $detail = $this->client->request('GET', 'orders/'.rawurlencode($fulfillment->provider_order_id));
                $this->saveSummary($fulfillment, $detail['order'] ?? []);
                $tracking = $this->client->request('GET', 'orders/'.rawurlencode($fulfillment->provider_order_id).'/tracking');
                $updates = [
                    'tracking' => Arr::only($tracking, ['waybillCode', 'tailTrackCode', 'carrier', 'lpName', 'headTrackingEvents', 'tailTrackingEvents']),
                    'synced_at' => now(), 'last_error' => null,
                ];
                if (filled($tracking['waybillCode'] ?? null) && $fulfillment->state !== 'cancelled') {
                    $updates['state'] = 'shipped';
                }
                $fulfillment->update($updates);
            } catch (Throwable) {
                $fulfillment->refresh()->update([
                    'state' => $fulfillment->provider_order_id ? $fulfillment->state : 'review',
                    'last_error' => 'Yoycol could not be synchronized. Reconcile before retrying any submission.',
                ]);
            }
        });
    }

    public function retry(YoycolFulfillment $fulfillment): void
    {
        Cache::lock('yoycol-fulfillment-'.$fulfillment->id, 600)->block(1, function () use ($fulfillment): void {
            $fulfillment->refresh();
            if ($fulfillment->provider_order_id || $fulfillment->state !== 'review' || $fulfillment->order->status !== 'completed') {
                throw ValidationException::withMessages(['order' => 'This order cannot be resubmitted.']);
            }
            $existing = $this->findRemoteOrder($fulfillment->store_order_sn);
            if ($existing) {
                $this->saveSummary($fulfillment, $existing);

                return;
            }
            $fulfillment->update(['state' => 'awaiting_capture', 'attempted_at' => null, 'last_error' => null]);
        });
        $this->process($fulfillment);
    }

    public function cancel(YoycolFulfillment $fulfillment): void
    {
        Cache::lock('yoycol-fulfillment-'.$fulfillment->id, 600)->block(1, function () use ($fulfillment): void {
            $fulfillment->refresh();
            if (! $fulfillment->provider_order_id) {
                throw ValidationException::withMessages(['order' => 'Reconcile this order before cancellation.']);
            }
            $detail = $this->client->request('GET', 'orders/'.rawurlencode($fulfillment->provider_order_id));
            if (data_get($detail, 'order.canCancel') !== true) {
                throw ValidationException::withMessages(['order' => 'Yoycol no longer allows cancellation. Contact Yoycol support.']);
            }
            $this->client->request('PUT', 'orders/'.rawurlencode($fulfillment->provider_order_id).'/cancel');
            $fulfillment->update(['state' => 'cancelled', 'last_error' => null]);
        });
    }

    private function findRemoteOrder(string $reference): ?array
    {
        $deadline = microtime(true) + 240;
        $match = null;
        for ($page = 1; $page <= 100; $page++) {
            if (microtime(true) > $deadline) {
                throw ValidationException::withMessages(['order' => 'Reconciliation timed out. No order was resubmitted.']);
            }
            $result = $this->client->request('GET', 'orders', ['page' => $page, 'size' => 50]);
            if (! is_array($result['records'] ?? null)) {
                throw ValidationException::withMessages(['order' => 'Invalid order list.']);
            }
            $matches = collect($result['records'])->where('storeOrderSn', $reference);
            if ($matches->count() > 1 || ($match && $matches->isNotEmpty())) {
                throw ValidationException::withMessages(['order' => 'Multiple supplier orders require review.']);
            }
            if ($matches->isNotEmpty()) {
                $match = $matches->first();
            }
            // Stop at an empty page, even if Yoycol caps a requested page size.
            if ($result['records'] === []) {
                return $match;
            }
        }
        throw ValidationException::withMessages(['order' => 'Order reconciliation limit reached.']);
    }

    private function saveSummary(YoycolFulfillment $fulfillment, array $summary): void
    {
        if (empty($summary['orderId']) || ($summary['storeOrderSn'] ?? '') !== $fulfillment->store_order_sn
            || ($fulfillment->provider_order_id && (string) $summary['orderId'] !== $fulfillment->provider_order_id)) {
            throw ValidationException::withMessages(['order' => 'The supplier order reference does not match.']);
        }
        $state = match (true) {
            in_array(strtolower($summary['status'] ?? ''), ['cancelled', 'canceled'], true) => 'cancelled',
            filled($summary['paidAt'] ?? null) => $fulfillment->state === 'shipped' ? 'shipped' : 'production',
            ($summary['canPay'] ?? false) === true => 'payment_required',
            default => 'review',
        };
        $fulfillment->update([
            'provider_order_id' => (string) $summary['orderId'], 'state' => $state,
            'provider_summary' => Arr::only($summary, ['orderId', 'orderNo', 'storeOrderSn', 'orderRealAmount', 'shippingFee', 'taxes', 'currency', 'status', 'statusLabel', 'paidAt', 'canPay', 'canCancel', 'needApprovalReason']),
            'synced_at' => now(), 'last_error' => null,
        ]);
    }
}
