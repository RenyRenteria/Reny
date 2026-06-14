<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoyalPassCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_product_checkout_creates_account_and_activates_royal_pass(): void
    {
        $response = $this->postJson('/checkout/paypal', [
            'identifier' => '+1 (555) 303-4040',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-ORDER-100',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('royal_status', 'royal_active');

        $user = User::where('phone', '15553034040')->firstOrFail();

        $this->assertTrue($user->fresh()->hasRoyalAccess());
        $this->assertNotNull($user->royal_ends_at);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-ORDER-100-merch',
            'product_key' => 'merch',
            'grants_royal_month' => true,
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'purchase',
            'resource_key' => 'PAYPAL-ORDER-100-merch',
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_started',
            'resource_key' => 'PAYPAL-ORDER-100-merch',
        ]);
    }

    public function test_refund_revokes_royal_access_and_logs_expiration(): void
    {
        $user = User::factory()->royal()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-REFUND-200-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);

        $this->postJson('/paypal/refund', [
            'provider_order_id' => $order->provider_order_id,
        ])
            ->assertOk()
            ->assertJsonPath('status', 'refunded')
            ->assertJsonPath('royal_status', 'royal_expired');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_expired',
            'resource_key' => 'PAYPAL-REFUND-200-merch',
        ]);
    }

    public function test_store_checkout_discloses_royal_month_grant_and_paypal_endpoint(): void
    {
        $this->get('/store')
            ->assertOk()
            ->assertSee('Every completed purchase activates Royal Pass for 1 month')
            ->assertSee('Complete with PayPal')
            ->assertSee(route('checkout.paypal'));
    }
}
