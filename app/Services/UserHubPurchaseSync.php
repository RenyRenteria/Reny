<?php

namespace App\Services;

use App\Models\BillingProfile;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use Carbon\CarbonImmutable;

class UserHubPurchaseSync
{
    public function __construct(private readonly TicketCodeService $tickets) {}

    /**
     * @param  array<string, mixed>  $capture
     */
    public function recordCompletedOrder(User $user, Order $order, array $capture): void
    {
        $user->refresh();
        $product = $this->product($order->product_key);

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
            UserUnlock::updateOrCreate([
                'user_id' => $user->id,
                'source_type' => 'order',
                'source_id' => $order->provider_order_id,
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
     * @return array{title: string, unlock_type?: string|null, event?: array<string, string>|null}
     */
    private function product(string $productKey): array
    {
        return match ($productKey) {
            'deluxe' => ['title' => 'Deluxe Digital Album', 'unlock_type' => 'album'],
            'singles' => ['title' => 'Singles / Digital Pack', 'unlock_type' => 'album'],
            'print' => ['title' => 'Numbered Art Print', 'unlock_type' => 'drop'],
            'concert' => [
                'title' => 'Reny Live - Studio Night',
                'event' => [
                    'title' => 'Reny Live - Studio Night',
                    'venue' => 'Panama City',
                    'address' => 'Panama City',
                    'starts_at' => '2026-08-24 20:00:00',
                ],
            ],
            'listening' => [
                'title' => 'Deluxe Preview Session',
                'event' => [
                    'title' => 'Deluxe Preview Session',
                    'venue' => 'Deluxe Listening Room',
                    'address' => 'Royal Stream',
                    'starts_at' => '2026-09-06 19:00:00',
                ],
            ],
            default => ['title' => str($productKey)->headline()->toString(), 'unlock_type' => null],
        };
    }

    /**
     * @param  array{event: array<string, string>}  $product
     */
    private function event(array $product): FanEvent
    {
        $event = $product['event'];
        $startsAt = CarbonImmutable::parse($event['starts_at'], 'America/Panama');

        return FanEvent::firstOrCreate([
            'title' => $event['title'],
            'starts_at' => $startsAt,
        ], [
            'venue' => $event['venue'],
            'address' => $event['address'],
            'timezone' => 'America/Panama',
            'status' => 'scheduled',
            'metadata' => [
                'source' => 'paypal_checkout',
            ],
        ]);
    }
}
