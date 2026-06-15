<?php

namespace Tests\Feature;

use App\Models\BillingProfile;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\PointLedgerEntry;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_dashboard_renders_profile_events_library_billing_points_and_purchases(): void
    {
        $user = User::factory()->royal()->create([
            'name' => 'Reny Member',
            'username' => 'renyfan',
            'country_code' => 'PA',
            'locale' => 'es',
            'timezone' => 'America/Panama',
            'preferred_currency' => 'USD',
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-DASH-100-deluxe',
            'product_key' => 'deluxe',
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);
        $event = FanEvent::create([
            'title' => 'Royal Listening Session',
            'venue' => 'Nexlab Stage',
            'address' => 'Panama City',
            'timezone' => 'America/Panama',
            'starts_at' => now()->addDays(5),
            'status' => 'scheduled',
        ]);

        BillingProfile::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_customer_id' => 'PAYER-DASH-100',
            'provider_subscription_id' => 'SUB-DASH-100',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
            'current_period_ends_at' => now()->addMonth(),
            'last_synced_at' => now(),
        ]);
        UserUnlock::create([
            'user_id' => $user->id,
            'order_id' => $order->id,
            'unlock_type' => 'album',
            'product_key' => 'deluxe',
            'title' => 'Deluxe Album',
            'source_type' => 'order',
            'source_id' => $order->provider_order_id,
            'status' => 'available',
            'unlocked_at' => now(),
        ]);
        $ticket = Ticket::create([
            'user_id' => $user->id,
            'event_id' => $event->id,
            'ticket_code_hash' => hash('sha256', 'dash-ticket'),
            'ticket_code_preview' => 'TCKT100',
            'holder_name' => $user->name,
            'status' => 'confirmed',
            'rsvp_status' => 'confirmed',
            'purchased_at' => now(),
        ]);
        PointLedgerEntry::create([
            'user_id' => $user->id,
            'event_type' => 'comment_posted',
            'source_type' => 'comment',
            'source_id' => 'comment-100',
            'delta' => 15,
            'status' => 'posted',
            'balance_after' => 15,
            'idempotency_key' => 'comment_posted:user-'.$user->id.':comment-100',
            'posted_at' => now(),
            'actor_type' => 'system',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Reny Member')
            ->assertSee('@renyfan')
            ->assertSee('Active Royal Member')
            ->assertSee('TKT-'.$ticket->id.'-', false)
            ->assertSeeInOrder([
                'Upcoming Events',
                'Royal Listening Session',
                'Library',
                'Deluxe Album',
                'Billing',
                'active',
                'Points',
                '15',
                'Purchases',
                'Deluxe',
                'Settings',
                'Manual request',
            ]);
    }

    public function test_account_dashboard_shows_clear_empty_states_for_new_open_user(): void
    {
        $user = User::factory()->create([
            'name' => 'New Fan',
            'royal_status' => 'open',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('New Fan')
            ->assertSee('Open Account')
            ->assertSee('Get your Royal Pass')
            ->assertSee('No upcoming events')
            ->assertSee('No purchases yet')
            ->assertSee('No billing profile')
            ->assertSee('No points yet')
            ->assertSee('Manual request');
    }
}
