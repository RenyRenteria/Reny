<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\User;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PayPalCheckoutFinalizer
{
    public function __construct(
        private readonly RoyalPassService $royalPass,
        private readonly UserHubPurchaseSync $purchaseSync,
    ) {}

    /**
     * Persist a verified PayPal capture exactly once.
     *
     * Both the browser capture callback and the signed PayPal webhook use this
     * transaction so a replay cannot grant access or create hub records twice.
     *
     * @param  Collection<int, Order>  $orders
     * @param  array{order_id: string, capture_id: string, payer_id?: string|null, payload?: array<string, mixed>, debug_id?: string|null, http_status?: int|null}  $capture
     * @return array{orders: Collection<int, Order>, finalized: bool}
     */
    public function finalize(Collection $orders, array $capture): array
    {
        $orderIds = $orders->pluck('id')->filter()->values();

        if ($orderIds->isEmpty()) {
            throw $this->invalidCapture('Create a PayPal order before capture.');
        }

        return DB::transaction(function () use ($capture, $orderIds): array {
            /** @var Collection<int, Order> $lockedOrders */
            $lockedOrders = Order::query()
                ->with('user')
                ->whereKey($orderIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($lockedOrders->count() !== $orderIds->count()) {
                throw $this->invalidCapture('This checkout is no longer available.');
            }

            $paypalOrderId = trim((string) ($capture['order_id'] ?? ''));
            $captureId = trim((string) ($capture['capture_id'] ?? ''));

            if ($paypalOrderId === '' || $captureId === '') {
                throw $this->invalidCapture('PayPal did not return a complete capture reference.');
            }

            if ($lockedOrders->contains(fn (Order $order): bool => $order->provider !== 'paypal'
                || ! str_starts_with($order->provider_order_id, "{$paypalOrderId}-"))) {
                throw $this->invalidCapture('PayPal returned a different order than this checkout.');
            }

            $alreadyCompleted = $lockedOrders->every(fn (Order $order): bool => $order->status === 'completed'
                && hash_equals((string) $order->provider_capture_id, $captureId));

            if ($alreadyCompleted) {
                return [
                    'orders' => $lockedOrders,
                    'finalized' => false,
                ];
            }

            if ($lockedOrders->contains(fn (Order $order): bool => ! in_array($order->status, ['pending', 'payment_review'], true)
                || (filled($order->provider_capture_id) && ! hash_equals((string) $order->provider_capture_id, $captureId)))) {
                throw $this->invalidCapture('This PayPal order has already been processed.');
            }

            $captureUsedElsewhere = Order::query()
                ->where('provider', 'paypal')
                ->whereNotIn('id', $orderIds)
                ->where(function ($query) use ($captureId, $paypalOrderId) {
                    $query
                        ->where('provider_capture_id', $captureId)
                        ->orWhere('provider_order_id', 'like', "{$paypalOrderId}-%");
                })
                ->exists();

            if ($captureUsedElsewhere) {
                throw $this->invalidCapture('This PayPal payment has already been recorded.');
            }

            $userIds = $lockedOrders->pluck('user_id')->filter()->unique();
            $user = $lockedOrders->first()?->user;

            if ($userIds->count() !== 1 || ! $user instanceof User) {
                throw $this->invalidCapture('This checkout is missing a valid customer account.');
            }

            $lockedOrders->each(function (Order $order) use ($capture, $captureId, $user): void {
                $order->forceFill([
                    'provider_capture_id' => $captureId,
                    'status' => 'completed',
                    'completed_at' => $order->completed_at ?? now(),
                ])->save();

                $this->royalPass->log($user, 'purchase', 'order', $order->provider_order_id, [
                    'product_key' => $order->product_key,
                    'provider' => 'paypal',
                    'paypal_capture_id' => $captureId,
                ]);

                $this->royalPass->grantMonth($user, $order);
                $this->purchaseSync->recordCompletedOrder($user, $order, $capture);
            });

            return [
                'orders' => $lockedOrders,
                'finalized' => true,
            ];
        });
    }

    private function invalidCapture(string $message): ValidationException
    {
        return ValidationException::withMessages([
            'paypal_order_id' => $message,
        ]);
    }
}
