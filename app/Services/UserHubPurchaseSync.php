<?php

namespace App\Services;

use App\Models\BillingProfile;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use App\Services\Commerce\ProductCatalog;
use Carbon\CarbonImmutable;

class UserHubPurchaseSync
{
    public function __construct(
        private readonly TicketCodeService $tickets,
        private readonly ProductCatalog $products,
    ) {}

    /**
     * @param  array<string, mixed>  $capture
     */
    public function recordCompletedOrder(User $user, Order $order, array $capture): void
    {
        $user->refresh();
        $product = $this->product($order, $user);

        BillingProfile::updateOrCreate([
            'user_id' => $user->id,
        ], [
            'provider' => 'paypal',
            'provider_customer_id' => $capture['payer_id'] ?? null,
            'provider_subscription_id' => $capture['subscription_id'] ?? null,
            'status' => $user->hasRoyalAccess() ? 'active' : 'inactive',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => $user->royal_ends_at,
            'last_synced_at' => now(),
            'metadata' => [
                'last_provider_order_id' => $order->provider_order_id,
                'last_capture_id' => $capture['capture_id'] ?? null,
            ],
        ]);

        if (($product['unlock_type'] ?? null) !== null) {
            $sourceType = (string) ($product['source_type'] ?? 'order');
            $sourceId = (string) ($product['source_id'] ?? $order->provider_order_id);

            UserUnlock::updateOrCreate([
                'user_id' => $user->id,
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'product_key' => $order->product_key,
            ], [
                'order_id' => $order->id,
                'unlock_type' => $product['unlock_type'],
                'title' => $product['title'],
                'status' => 'available',
                'unlocked_at' => now(),
                'revoked_at' => null,
                'metadata' => [
                    'provider' => $order->provider,
                    'capture_id' => $capture['capture_id'] ?? null,
                    'provider_order_id' => $order->provider_order_id,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                ],
            ]);
        }

        if (($product['event'] ?? null) !== null && ! Ticket::query()->where('order_id', $order->id)->exists()) {
            $event = $this->event($product);
            $this->tickets->issue($user, $event, $user->name, order: $order);
        }

        PublicCmsContentService::forgetCachedUserPayloads($user);
    }

    public function recordRefund(Order $order): void
    {
        UserUnlock::query()
            ->where('order_id', $order->id)
            ->update([
                'status' => 'revoked',
                'revoked_at' => now(),
                'updated_at' => now(),
            ]);

        Ticket::query()
            ->where('order_id', $order->id)
            ->whereIn('status', ['reserved', 'confirmed', 'checked_in'])
            ->update([
                'status' => 'refunded',
                'updated_at' => now(),
            ]);

        if ($order->user) {
            $order->user->billingProfile()->update([
                'status' => 'refunded',
                'last_synced_at' => now(),
            ]);

            PublicCmsContentService::forgetCachedUserPayloads($order->user);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function product(Order $order, User $user): array
    {
        $snapshot = data_get($order->metadata, 'product');

        if (is_array($snapshot)) {
            return [
                'key' => $snapshot['key'] ?? $order->product_key,
                'title' => $snapshot['title'] ?? str($order->product_key)->headline()->toString(),
                'unlock_type' => $snapshot['unlock_type'] ?? null,
                'source_type' => $snapshot['source_type'] ?? 'order',
                'source_id' => $snapshot['source_id'] ?? $order->provider_order_id,
                'event' => $snapshot['event'] ?? null,
            ];
        }

        return $this->products->find($order->product_key, $user) ?? [
            'key' => $order->product_key,
            'title' => str($order->product_key)->headline()->toString(),
            'unlock_type' => null,
            'source_type' => 'order',
            'source_id' => $order->provider_order_id,
            'event' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $product
     */
    private function event(array $product): FanEvent
    {
        $event = $product['event'];
        $timezone = $event['timezone'] ?? 'America/Panama';
        $startsAt = CarbonImmutable::parse($event['starts_at'], $timezone);

        return FanEvent::firstOrCreate([
            'title' => $event['title'],
            'starts_at' => $startsAt,
        ], [
            'venue' => $event['venue'],
            'address' => $event['address'],
            'timezone' => $timezone,
            'status' => 'scheduled',
            'metadata' => [
                'source' => 'paypal_checkout',
                'store_event_key' => $product['key'] ?? null,
            ],
        ]);
    }
}
