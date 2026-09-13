<?php

namespace App\Services\Commerce;

use App\Models\EditorialContent;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TicketInventory
{
    public function tracks(EditorialContent $content): bool
    {
        return data_get($content->metadata, 'inventory_tracking') === 'orders';
    }

    public function remaining(EditorialContent $content, bool $lock = false): int
    {
        $orders = Order::query()
            ->where('product_key', $content->purchase_key)
            ->whereNotIn('status', ['cancelled', 'failed', 'refunded'])
            ->where(function ($query): void {
                $query->where('status', '!=', 'pending')
                    ->orWhere('provider', '!=', 'paypal')
                    ->orWhereNull('metadata->inventory->expires_at')
                    ->orWhere('metadata->inventory->expires_at', '>', now()->toISOString());
            });

        // Locking reads see the latest committed orders even on MySQL REPEATABLE READ.
        $used = $lock ? $orders->lockForUpdate()->get(['id'])->count() : $orders->count();

        return max(0, (int) data_get($content->metadata, 'inventory', 0) - $used);
    }

    /**
     * Called inside the transaction that inserts orders, so simultaneous buyers
     * share the same stock lock. Repeated keys represent multiple tickets.
     *
     * @param  Collection<int, array<string, mixed>>  $products
     */
    public function reserve(Collection $products): void
    {
        $groups = $products->where('inventory_tracked', true)->groupBy('source_id')->sortKeys();

        foreach ($groups as $contentId => $items) {
            $content = EditorialContent::query()->lockForUpdate()->findOrFail($contentId);

            if ($this->remaining($content, lock: true) < $items->count()) {
                throw ValidationException::withMessages([
                    'product_keys' => 'The requested tickets are no longer available. Please update your bag.',
                ]);
            }
        }
    }

    /**
     * An abandoned PayPal checkout holds stock for 30 minutes. Before contacting
     * PayPal, pin its unexpired reservation until capture is resolved; it cannot
     * expire or be canceled while a payment might already be in flight.
     *
     * @param  Collection<int, Order>  $orders
     */
    public function beginCapture(Collection $orders): void
    {
        $tracked = $orders->filter(fn (Order $order): bool => is_array(data_get($order->metadata, 'inventory')));

        if ($tracked->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($tracked): void {
            $contentIds = $tracked->map(fn (Order $order) => data_get($order->metadata, 'product.source_id'))->unique()->sort();
            EditorialContent::query()->whereKey($contentIds)->orderBy('id')->lockForUpdate()->get();
            $freshOrders = Order::query()->whereKey($tracked->pluck('id'))->orderBy('id')->lockForUpdate()->get();

            foreach ($freshOrders as $order) {
                $expiresAt = data_get($order->metadata, 'inventory.expires_at');

                if ($order->status !== 'pending' || ($expiresAt && Carbon::parse($expiresAt)->lte(now()))) {
                    throw ValidationException::withMessages([
                        'paypal_order_id' => 'This ticket reservation has expired or is no longer pending. Please start checkout again.',
                    ]);
                }

                $metadata = $order->metadata;
                $metadata['inventory'] = ['expires_at' => null, 'capture_started' => true];
                $order->forceFill(['metadata' => $metadata])->save();
            }
        });
    }
}
