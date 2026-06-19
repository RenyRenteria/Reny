<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_stats_page_uses_current_month_views_sales_and_live_royal_members(): void
    {
        $this->travelTo(now()->setDate(2026, 6, 19)->setTime(18, 17));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $activeRoyal = User::factory()->royal()->create();
        User::factory()->royalGrace()->create();
        User::factory()->expiredRoyal()->create();

        AccessEvent::forceCreate([
            'event_name' => 'page_view',
            'resource_type' => 'page',
            'resource_key' => 'home',
            'created_at' => now()->subDay(),
        ]);
        AccessEvent::forceCreate([
            'event_name' => 'page_view',
            'resource_type' => 'page',
            'resource_key' => 'home',
            'created_at' => now()->subMonth(),
        ]);
        AccessEvent::forceCreate([
            'event_name' => 'permission_denied',
            'resource_type' => 'access_gate',
            'resource_key' => 'royal',
            'created_at' => now(),
        ]);
        AccessEvent::forceCreate([
            'event_name' => 'permission_denied',
            'resource_type' => 'access_gate',
            'resource_key' => 'royal',
            'created_at' => now()->subMonth(),
        ]);

        Order::forceCreate([
            'user_id' => $activeRoyal->id,
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-CURRENT',
            'product_key' => 'royal-pass',
            'amount_cents' => 12000000000000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now(),
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-OLD',
            'product_key' => 'royal-pass',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->subMonth(),
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-REFUNDED',
            'product_key' => 'royal-pass',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'refunded_at' => now(),
            'created_at' => now(),
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-FUTURE',
            'product_key' => 'royal-pass',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => now()->addDay(),
        ]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Homepage Views')
            ->assertSee('>1<', false)
            ->assertSee('Paywall Views')
            ->assertSee('Royal Members')
            ->assertSee('>2<', false)
            ->assertSee('$120,000,000,000')
            ->assertSee('120 billion')
            ->assertSee('STATS')
            ->assertSee('data-admin-nav="stats"', false)
            ->assertDontSee('data-admin-nav="dashboard"', false);
    }

    public function test_admin_stats_page_marks_empty_values_in_red(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('class="is-zero"', false)
            ->assertSee('>0<', false);
    }

    public function test_analytics_endpoint_records_homepage_and_paywall_views(): void
    {
        $this->postJson(route('analytics.events.store'), [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'result' => 'viewed',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertCreated();

        $this->postJson(route('analytics.events.store'), [
            'name' => 'permission_denied',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'item_type' => 'access_gate',
                'item_id' => 'royal',
                'section' => 'royal',
                'result' => 'blocked',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertCreated();

        $this->assertDatabaseHas('access_events', [
            'event_name' => 'page_view',
            'resource_type' => 'page',
            'resource_key' => 'home',
        ]);
        $this->assertDatabaseHas('access_events', [
            'event_name' => 'permission_denied',
            'resource_type' => 'access_gate',
            'resource_key' => 'royal',
        ]);
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
