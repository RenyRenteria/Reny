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
                'title' => 'Reny Music',
                'referrer' => null,
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

    public function test_analytics_endpoint_rejects_untracked_events_and_unexpected_payload_shape(): void
    {
        $this->postJson(route('analytics.events.store'), [
            'name' => 'paywall_cta_clicked',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'result' => 'clicked',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);

        $this->postJson(route('analytics.events.store'), [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'result' => 'viewed',
                'debug' => str_repeat('x', 512),
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable();

        $this->postJson(route('analytics.events.store'), [
            'name' => 'permission_denied',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'item_type' => 'access_gate',
                'item_id' => str_repeat('royal', 40),
                'section' => 'royal',
                'result' => 'blocked',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payload.item_id']);

        $this->assertDatabaseCount('access_events', 0);
    }

    public function test_analytics_endpoint_throttles_repeated_posts_by_ip(): void
    {
        $payload = [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'title' => 'Reny Music',
                'referrer' => null,
                'result' => 'viewed',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
                ->postJson(route('analytics.events.store'), $payload)
                ->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertStatus(429);

        $this->assertDatabaseCount('access_events', 120);
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
