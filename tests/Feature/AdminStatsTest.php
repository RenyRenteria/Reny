<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\OrderRefund;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_original_monthly_kpis_render_before_the_current_reports_dashboard(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-07 12:00:00', 'America/Panama'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->royal()->create();
        User::factory()->royalGrace()->create();
        User::factory()->expiredRoyal()->create();

        AccessEvent::forceCreate([
            'event_name' => 'page_view',
            'resource_type' => 'page',
            'resource_key' => 'home',
            'created_at' => $this->utc('2026-07-02 10:00:00'),
            'updated_at' => $this->utc('2026-07-02 10:00:00'),
        ]);
        AccessEvent::forceCreate([
            'event_name' => 'permission_denied',
            'resource_type' => 'access_gate',
            'resource_key' => 'royal',
            'created_at' => $this->utc('2026-07-03 10:00:00'),
            'updated_at' => $this->utc('2026-07-03 10:00:00'),
        ]);
        AccessEvent::forceCreate([
            'event_name' => 'page_view',
            'resource_type' => 'page',
            'resource_key' => 'home',
            'created_at' => $this->utc('2026-06-30 23:59:59'),
            'updated_at' => $this->utc('2026-06-30 23:59:59'),
        ]);

        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'MONTHLY-KPI-USD',
            'product_key' => 'royal-pass',
            'amount_cents' => 12500,
            'currency' => 'USD',
            'status' => 'completed',
            'created_at' => $this->utc('2026-07-04 10:00:00'),
            'updated_at' => $this->utc('2026-07-04 10:00:00'),
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'MONTHLY-KPI-EUR',
            'product_key' => 'royal-pass',
            'amount_cents' => 90000,
            'currency' => 'EUR',
            'status' => 'completed',
            'created_at' => $this->utc('2026-07-04 10:00:00'),
            'updated_at' => $this->utc('2026-07-04 10:00:00'),
        ]);

        $this->actingAsAdmin($admin);

        $response = $this->get(route('admin.dashboard', ['preset' => '7d']))
            ->assertOk()
            ->assertViewHas('monthlyStats', fn (array $stats): bool => $stats['homepageViews']['value'] === 1
                && $stats['paywallViews']['value'] === 1
                && $stats['royalMembers']['value'] === 2
                && $stats['monthlySales']['value'] === 12500)
            ->assertSee('Homepage Views')
            ->assertSee('Paywall Views')
            ->assertSee('Royal Members')
            ->assertSee('Monthly Sales')
            ->assertSee('$125')
            ->assertSee('Ventas netas')
            ->assertSee('Productos')
            ->assertSee('Contenido')
            ->assertSee('Shows');

        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-report-filter'), strpos($html, 'data-monthly-kpi="homepageViews"'));
        $this->assertLessThan(strpos($html, 'Ventas netas'), strpos($html, 'Homepage Views'));
    }

    public function test_admin_reports_apply_custom_range_previous_period_refunds_and_multi_currency(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-07-07 12:00:00', 'America/Panama'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->royal()->create();
        User::factory()->royalGrace()->create();
        User::factory()->expiredRoyal()->create();

        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-USD',
            'provider_capture_id' => 'CAPTURE-USD',
            'product_key' => 'vinyl',
            'amount_cents' => 10000,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-07-02 12:00:00'),
            'created_at' => $this->utc('2026-07-02 11:55:00'),
            'metadata' => ['product' => ['title' => 'Vinyl dorado', 'type' => 'merch']],
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-EUR',
            'provider_capture_id' => 'CAPTURE-EUR',
            'product_key' => 'show-madrid',
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-07-03 13:00:00'),
            'created_at' => $this->utc('2026-07-03 12:55:00'),
            'metadata' => ['product' => ['title' => 'Show Madrid', 'type' => 'event']],
        ]);
        $refundedOrder = Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-REFUNDED',
            'product_key' => 'royal-pass',
            'amount_cents' => 4000,
            'currency' => 'USD',
            'status' => 'refunded',
            'completed_at' => $this->utc('2026-06-20 10:00:00'),
            'refunded_at' => $this->utc('2026-07-04 10:00:00'),
            'refund_amount_cents' => 4000,
            'created_at' => $this->utc('2026-06-20 09:55:00'),
        ]);
        OrderRefund::forceCreate([
            'order_id' => $refundedOrder->id,
            'provider_refund_id' => 'REFUND-USD',
            'amount_cents' => 4000,
            'currency' => 'USD',
            'refunded_at' => $this->utc('2026-07-04 10:00:00'),
        ]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-PREVIOUS',
            'product_key' => 'royal-pass',
            'amount_cents' => 5000,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-06-27 10:00:00'),
            'created_at' => $this->utc('2026-06-27 09:55:00'),
        ]);
        foreach (['pending', 'failed', 'cancelled'] as $status) {
            Order::forceCreate([
                'provider' => 'paypal',
                'provider_order_id' => 'ORDER-'.strtoupper($status),
                'product_key' => 'ignored',
                'amount_cents' => 999999,
                'currency' => 'USD',
                'status' => $status,
                'created_at' => $this->utc('2026-07-05 10:00:00'),
            ]);
        }
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-END-BOUNDARY',
            'product_key' => 'ignored-boundary',
            'amount_cents' => 7000,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-07-08 00:00:00'),
            'created_at' => $this->utc('2026-07-08 00:00:00'),
        ]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.dashboard', [
            'preset' => 'custom',
            'start' => '2026-07-01',
            'end' => '2026-07-07',
        ]))
            ->assertOk()
            ->assertSee('Ventas netas')
            ->assertSee('USD 60.00')
            ->assertSee('EUR 50.00')
            ->assertSee('Órdenes completadas')
            ->assertSee('Snapshot actual · sin comparación histórica')
            ->assertSee('America/Panama')
            ->assertSee('value="custom" checked', false)
            ->assertSee('value="2026-07-01"', false)
            ->assertSee('value="2026-07-07"', false)
            ->assertSee('Vinyl dorado')
            ->assertDontSee('999,999')
            ->assertSee('STATS')
            ->assertSee('data-admin-nav="stats"', false)
            ->assertDontSee('data-admin-nav="dashboard"', false);
    }

    public function test_admin_reports_distinguish_successful_zero_empty_and_unavailable_states(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('USD 0.00')
            ->assertSee('La consulta terminó correctamente: no hubo ventas ni reembolsos')
            ->assertSee('No disponible: todavía no existen eventos de interacción persistidos.')
            ->assertSee('No hubo ventas ni reembolsos de productos en el rango.')
            ->assertDontSee('No pudimos consultar este módulo');
    }

    public function test_one_reporting_module_failure_does_not_hide_healthy_modules(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);
        Schema::drop('access_events');

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Ventas netas')
            ->assertSee('USD 0.00')
            ->assertSee('Embudo no disponible')
            ->assertSee('Contenido no disponible');
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

    public function test_analytics_events_are_versioned_anonymous_idempotent_and_do_not_store_referrers(): void
    {
        $payload = [
            'name' => 'store_checkout_started',
            'schema_version' => 1,
            'session_id' => 'anonymous-session-1',
            'event_id' => 'event-checkout-1',
            'payload' => [
                'screen' => 'store',
                'path' => '/store',
                'item_type' => 'checkout',
                'item_id' => 'bag',
                'result' => 'opened',
                'referrer' => 'https://example.test/?email=private@example.test',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $this->postJson(route('analytics.events.store'), $payload)->assertCreated();
        $this->postJson(route('analytics.events.store'), $payload)
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $event = AccessEvent::query()->sole();

        $this->assertSame(1, $event->schema_version);
        $this->assertSame('anonymous-session-1', $event->session_id);
        $this->assertSame('client:'.substr(hash('sha256', 'event-checkout-1'), 0, 57), $event->idempotency_key);
        $this->assertNull($event->user_id);
        $this->assertSame('opened', $event->result);
        $this->assertNotNull($event->occurred_at);
        $this->assertArrayNotHasKey('referrer', $event->metadata);
        $this->assertArrayNotHasKey('path', $event->metadata);
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
            'payload' => 'not-an-object',
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payload']);

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
                'screen' => ['nested' => 'bad'],
                'path' => '/',
                'item_type' => 'access_gate',
                'item_id' => 'royal',
                'section' => 'royal',
                'result' => 'blocked',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payload.screen']);

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

        $this->postJson(route('analytics.events.store'), [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'private@example.test',
                'path' => '/account/private@example.test',
                'result' => 'viewed',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['payload.screen']);

        $this->postJson(route('analytics.events.store'), [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'title' => str_repeat('A', 3000),
                'result' => 'viewed',
            ],
            'timestamp' => now()->toIso8601String(),
        ])->assertStatus(413);

        $this->assertDatabaseCount('access_events', 0);
    }

    public function test_paypal_browser_analytics_payloads_are_accepted_and_provider_id_is_not_stored(): void
    {
        $baseEvent = [
            'schema_version' => 1,
            'session_id' => 'paypal-browser-session',
            'payload' => [
                'screen' => 'store_checkout',
                'path' => '/store/checkout/royal',
                'item_type' => 'checkout',
                'item_id' => 'paypal',
                'method' => 'paypal',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $this->postJson(route('analytics.events.store'), [
            ...$baseEvent,
            'name' => 'store_payment_started',
            'event_id' => 'paypal-payment-started',
            'payload' => [
                ...$baseEvent['payload'],
                'item_type' => 'payment_attempt',
                'checkout_state' => 'payment_started',
                'result' => 'payment_started',
                'item_count' => 1,
                'currency' => 'USD',
            ],
        ])->assertCreated();

        $this->postJson(route('analytics.events.store'), [
            ...$baseEvent,
            'name' => 'store_payment_succeeded',
            'event_id' => 'paypal-payment-succeeded',
            'payload' => [
                ...$baseEvent['payload'],
                'item_type' => 'payment_method',
                'checkout_state' => 'payment_success',
                'result' => 'payment_success',
                'paypal_order_id' => 'PAYPAL-ORDER-123',
            ],
        ])->assertCreated();

        $started = AccessEvent::query()->where('event_name', 'store_payment_started')->sole();
        $succeeded = AccessEvent::query()->where('event_name', 'store_payment_succeeded')->sole();

        $this->assertSame('USD', $started->metadata['currency']);
        $this->assertArrayNotHasKey('paypal_order_id', $started->metadata);
        $this->assertArrayNotHasKey('paypal_order_id', $succeeded->metadata);
    }

    public function test_non_provider_failures_keep_distinct_analytics_events(): void
    {
        $events = [
            'store_checkout_validation_failed' => 'validation_failed',
            'store_payment_canceled' => 'canceled',
            'store_payment_unavailable' => 'unavailable',
        ];

        foreach ($events as $eventName => $checkoutState) {
            $this->postJson(route('analytics.events.store'), [
                'name' => $eventName,
                'schema_version' => 1,
                'session_id' => 'checkout-taxonomy-session',
                'event_id' => 'checkout-'.$checkoutState,
                'payload' => [
                    'screen' => 'store_checkout',
                    'path' => '/store/checkout/royal',
                    'item_type' => 'payment_method',
                    'item_id' => 'paypal',
                    'method' => 'paypal',
                    'checkout_state' => $checkoutState,
                    'result' => $checkoutState,
                    'reason' => $checkoutState,
                ],
                'timestamp' => now()->toIso8601String(),
            ])->assertCreated();
        }

        $this->assertDatabaseCount('access_events', 3);
        $this->assertDatabaseMissing('access_events', ['event_name' => 'store_payment_failed']);

        foreach ($events as $eventName => $checkoutState) {
            $this->assertDatabaseHas('access_events', [
                'event_name' => $eventName,
                'result' => $checkoutState,
            ]);
        }
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

        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
                ->postJson(route('analytics.events.store'), $payload)
                ->assertCreated();
        }

        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->postJson(route('analytics.events.store'), $payload)
            ->assertStatus(429);

        $this->assertDatabaseCount('access_events', 60);
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    private function utc(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, 'America/Panama')->utc();
    }
}
