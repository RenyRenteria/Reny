<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Services\Admin\ReportDashboardService;
use App\Support\Reports\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_period_presets_and_custom_range_use_equal_previous_periods_in_business_timezone(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));

        foreach (['7d', '30d', '90d', '12m'] as $preset) {
            $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => $preset]));

            $this->assertSame('America/Panama', $period->timezone);
            $this->assertSame(
                $period->start->diffInMicroseconds($period->end),
                $period->previousStart->diffInMicroseconds($period->previousEnd),
            );
            $this->assertTrue($period->previousEnd->lessThan($period->start));
        }

        $custom = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-06',
        ]));

        $this->assertSame('2026-08-01 00:00:00', $custom->start->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-06 23:59:59', $custom->end->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-26', $custom->previousStart->toDateString());
        $this->assertSame('2026-07-31', $custom->previousEnd->toDateString());
    }

    public function test_dashboard_calculates_net_sales_refunds_comparisons_and_currencies_from_canonical_orders(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        User::factory()->create(['created_at' => now()->subDay()]);
        User::factory()->create(['created_at' => now()->subDays(10)]);
        User::factory()->royal()->create(['created_at' => now()->subYear()]);

        $this->order('USD-CURRENT', 10_000, 'USD', '2026-08-04 12:00:00');
        $this->order('USD-REFUNDED', 5_000, 'USD', '2026-08-03 12:00:00', '2026-08-05 12:00:00');
        $this->order('USD-OLD-REFUND', 2_000, 'USD', '2026-07-10 12:00:00', '2026-08-04 12:00:00');
        $this->order('EUR-CURRENT', 4_000, 'EUR', '2026-08-02 12:00:00');
        $this->order('USD-PREVIOUS', 4_000, 'USD', '2026-07-28 12:00:00');
        $this->order('FAILED', 99_900, 'USD', '2026-08-04 12:00:00', status: 'failed');
        $this->order('PENDING', 88_800, 'USD', '2026-08-04 12:00:00', status: 'pending');

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-06',
        ]));
        $dashboard = app(ReportDashboardService::class)->dashboard($period);
        $sales = collect($dashboard['kpis']['sales'])->keyBy('currency');

        $this->assertSame(8_000, $sales['USD']['current_cents']);
        $this->assertSame(4_000, $sales['USD']['previous_cents']);
        $this->assertSame(4_000, $sales['EUR']['current_cents']);
        $this->assertSame(0, $sales['EUR']['previous_cents']);
        $this->assertSame(3, $dashboard['kpis']['orders']['current']);
        $this->assertSame(1, $dashboard['kpis']['orders']['previous']);
        $this->assertSame(1, $dashboard['kpis']['users']['current']);
        $this->assertSame(1, $dashboard['kpis']['users']['previous']);
        $this->assertSame(1, $dashboard['kpis']['royals']['current']);
        $this->assertNull($dashboard['kpis']['royals']['variation']);
        $charts = collect($dashboard['sales_charts'])->keyBy('currency');
        $this->assertSame(8_000, collect($charts['USD']['points'])->sum('current_cents'));
        $this->assertSame(4_000, collect($charts['USD']['points'])->sum('previous_cents'));
        $this->assertSame(4_000, collect($charts['EUR']['points'])->sum('current_cents'));

        $this->actingAsAdmin($admin);
        $this->get(route('admin.dashboard', $period->query()))
            ->assertOk()
            ->assertSee('Net sales')
            ->assertSee('USD 80.00')
            ->assertSee('EUR 40.00')
            ->assertDontSee('USD 120.00')
            ->assertSee('Current snapshot · comparison N/A')
            ->assertSee('value="custom" checked', false)
            ->assertSee('value="2026-08-01"', false)
            ->assertSee('value="2026-08-06"', false)
            ->assertSee('America/Panama')
            ->assertSee('data-report-skeleton', false)
            ->assertSee('stats-chart-tooltip', false)
            ->assertSee('Comparison:')
            ->assertSee('data-admin-nav="stats"', false)
            ->assertDontSee('data-admin-nav="dashboard"', false);
    }

    public function test_long_custom_ranges_keep_current_and_previous_sales_buckets_aligned(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2025-03-01',
            'to' => '2025-05-30',
        ]));
        $chart = collect(app(ReportDashboardService::class)->dashboard($period)['sales_charts'])
            ->firstWhere('currency', 'USD');

        $this->assertSame('monthly', $chart['granularity']);
        $this->assertCount(3, $chart['points']);
        $this->assertSame($period->start->toIso8601String(), $chart['points'][0]['current_start']);
        $this->assertSame($period->end->toIso8601String(), $chart['points'][2]['current_end']);
        $this->assertSame($period->previousStart->toIso8601String(), $chart['points'][0]['previous_start']);
        $this->assertSame($period->previousEnd->toIso8601String(), $chart['points'][2]['previous_end']);
        $this->assertNotContains(null, collect($chart['points'])->pluck('current_start')->all());
        $this->assertNotContains(null, collect($chart['points'])->pluck('previous_start')->all());
    }

    public function test_dashboard_funnel_deduplicates_sessions_and_content_ranking_keeps_metric_types_separate(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->analytics('page_view', 'page', 'store', 'session-a', '2026-08-01 10:00:00');
        $this->analytics('store_product_opened', 'product', 'album-one', 'session-a', '2026-08-01 10:01:00');
        $this->analytics('page_view', 'page', 'store', 'session-b', '2026-08-01 10:02:00');
        $this->analytics('store_checkout_started', 'checkout', 'album-one', 'session-a', '2026-08-02 10:00:00');
        $this->analytics('store_checkout_started', 'checkout', 'album-one', 'session-a', '2026-08-02 10:00:01');
        $this->analytics('store_payment_succeeded', 'payment', 'paypal', 'session-a', '2026-08-02 10:05:00');
        $this->analytics('store_payment_succeeded', 'payment', 'paypal', 'session-a', '2026-08-02 10:05:01');
        $this->analytics('store_payment_failed', 'payment', 'paypal', 'session-b', '2026-08-02 11:00:00');
        $this->analytics('store_payment_canceled', 'payment', 'paypal', 'session-c', '2026-08-02 11:01:00');
        $this->analytics('store_payment_unavailable', 'payment', 'card', 'session-d', '2026-08-02 11:02:00');
        $this->analytics('store_checkout_validation_failed', 'checkout', 'album-one', 'session-e', '2026-08-02 11:03:00');
        $this->analytics('music_play_started', 'music', 'track-one', 'session-a', '2026-08-03 10:00:00', 'Track One');
        $this->analytics('music_play_started', 'music', 'track-one', 'session-a', '2026-08-03 10:01:00', 'Track One');
        $this->analytics('video_play_started', 'video', 'video-one', 'session-b', '2026-08-03 11:00:00', 'Video One');

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-06',
        ]));
        $dashboard = app(ReportDashboardService::class)->dashboard($period);

        $this->assertSame(2, $dashboard['funnel']['stages'][0]['sessions']);
        $this->assertSame(3, $dashboard['funnel']['stages'][0]['events']);
        $this->assertSame(1, $dashboard['funnel']['stages'][1]['sessions']);
        $this->assertSame(50.0, $dashboard['funnel']['stages'][1]['conversion']['value']);
        $this->assertSame(1, $dashboard['funnel']['stages'][2]['sessions']);
        $this->assertSame(100.0, $dashboard['funnel']['stages'][2]['conversion']['value']);
        $this->assertSame(1, $dashboard['funnel']['failures']['sessions']);
        $this->assertSame(1, $dashboard['funnel']['failures']['events']);
        $this->assertSame('partial', $dashboard['funnel']['coverage']['status']);
        $this->assertSame('Track One', $dashboard['content'][0]['title']);
        $this->assertSame('music', $dashboard['content'][0]['type']);
        $this->assertSame(2, $dashboard['content'][0]['events']);
        $this->assertSame(1, $dashboard['content'][0]['sessions']);

        $this->actingAsAdmin($admin);
        $this->get(route('admin.dashboard', $period->query()))
            ->assertOk()
            ->assertSee('2 sessions')
            ->assertSee('50% from prior step')
            ->assertSee('Track One')
            ->assertSee('Video One')
            ->assertSee('Partial coverage');
    }

    public function test_show_report_uses_canonical_rsvps_tickets_and_check_ins_and_marks_unavailable_sources(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $user = User::factory()->create(['created_at' => now()->subYear()]);
        $show = FanEvent::forceCreate([
            'title' => 'Panama Live',
            'venue' => 'Arena',
            'timezone' => 'America/Panama',
            'starts_at' => CarbonImmutable::parse('2026-08-06 20:00:00', 'America/Panama'),
            'status' => 'scheduled',
            'metadata' => ['store_event_key' => 'panama-live'],
        ]);
        $futureShow = FanEvent::forceCreate([
            'title' => 'Future Canonical Show',
            'venue' => 'Future Arena',
            'timezone' => 'America/Panama',
            'starts_at' => CarbonImmutable::parse('2026-09-10 20:00:00', 'America/Panama'),
            'status' => 'scheduled',
            'metadata' => ['store_event_key' => 'future-canonical'],
        ]);
        $order = $this->order('SHOW-ORDER', 2_500, 'USD', '2026-08-02 12:00:00');
        $order->forceFill(['user_id' => $user->id])->save();

        $this->ticket($user, $show, null, '2026-08-01 12:00:00');
        $this->ticket($user, $show, $order, '2026-08-02 12:00:00', '2026-08-06 19:55:00');
        Rsvp::forceCreate([
            'event_key' => 'panama-live',
            'event_name' => 'Panama Live',
            'name' => 'Private Name',
            'email' => 'private@example.test',
            'country' => 'Panama',
            'created_at' => CarbonImmutable::parse('2026-08-03 12:00:00', 'America/Panama'),
        ]);
        Rsvp::forceCreate([
            'event_key' => 'future-canonical',
            'event_name' => 'Stale RSVP Label',
            'name' => 'Canonical Match',
            'email' => 'canonical@example.test',
            'country' => 'Panama',
            'created_at' => CarbonImmutable::parse('2026-08-03 12:30:00', 'America/Panama'),
        ]);
        Rsvp::forceCreate([
            'event_key' => 'free-unlinked',
            'event_name' => 'Free Unlinked Show',
            'name' => 'Another Private Name',
            'email' => 'another-private@example.test',
            'country' => 'Panama',
            'created_at' => CarbonImmutable::parse('2026-08-03 13:00:00', 'America/Panama'),
        ]);

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-06',
        ]));
        $dashboard = app(ReportDashboardService::class)->dashboard($period);
        $shows = collect($dashboard['shows'])->keyBy('key');

        $this->assertSame(3, $shows['panama-live']['rsvps']);
        $this->assertSame(1, $shows['panama-live']['tickets']);
        $this->assertSame(1, $shows['panama-live']['check_ins']);
        $this->assertSame(33.3, $shows['panama-live']['rsvp_to_ticket']['value']);
        $this->assertSame(100.0, $shows['panama-live']['ticket_to_check_in']['value']);
        $this->assertSame($futureShow->title, $shows['future-canonical']['title']);
        $this->assertSame($futureShow->starts_at->toIso8601String(), $shows['future-canonical']['starts_at']);
        $this->assertSame(1, $shows['future-canonical']['rsvps']);
        $this->assertSame(0, $shows['future-canonical']['tickets']);
        $this->assertSame(0, $shows['future-canonical']['check_ins']);
        $this->assertTrue($shows['future-canonical']['check_in_available']);
        $this->assertNull($shows['free-unlinked']['tickets']);
        $this->assertNull($shows['free-unlinked']['check_ins']);
        $this->assertFalse($shows['free-unlinked']['check_in_available']);
    }

    public function test_each_csv_export_matches_the_active_range_and_excludes_personal_data(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN,
            'email' => 'admin-private@example.test',
        ]);
        $this->order('CSV-ORDER', 1_250, 'USD', '2026-08-04 12:00:00');
        $this->analytics('music_play_started', 'music', 'csv-track', 'session-csv', '2026-08-04 12:00:00', 'CSV Track');
        $this->actingAsAdmin($admin);

        $expectedHeaders = [
            'summary' => 'metric,currency,current_value',
            'sales' => 'currency,granularity,current_period',
            'funnel' => 'stage,unique_sessions,total_events',
            'products' => 'product_key,product_title,product_type',
            'content' => 'content_type,content_id,title',
            'shows' => 'show_key,show_title,starts_at',
        ];

        foreach ($expectedHeaders as $report => $header) {
            $response = $this->get(route('admin.reports.export', [
                'report' => $report,
                'period' => 'custom',
                'from' => '2026-08-01',
                'to' => '2026-08-06',
            ]));

            $response->assertOk()
                ->assertHeader('content-type', 'text/csv; charset=UTF-8')
                ->assertDownload($report.'-2026-08-01-2026-08-06.csv');
            $csv = $response->streamedContent();
            $this->assertStringStartsWith($header, $csv);
            $this->assertStringNotContainsString('admin-private@example.test', $csv);
            $this->assertStringNotContainsString('provider_order_id', $csv);
        }
    }

    public function test_sales_csv_rows_have_iso_dates_and_exactly_match_dashboard_chart_values(): void
    {
        $this->order('CSV-CURRENT', 1_250, 'USD', '2026-08-04 12:00:00');
        $this->order('CSV-PREVIOUS', 400, 'USD', '2026-07-28 12:00:00');
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', [
            'period' => 'custom',
            'from' => '2026-08-01',
            'to' => '2026-08-06',
        ]));
        $reports = app(ReportDashboardService::class);
        $dashboard = $reports->dashboard($period);
        $export = $reports->export('sales', $period);
        $expectedRows = collect($dashboard['sales_charts'])
            ->flatMap(fn (array $chart): array => collect($chart['points'])
                ->map(fn (array $point): array => [
                    $chart['currency'],
                    $chart['granularity'],
                    $point['current_start'],
                    $point['current_end'],
                    number_format($point['current_cents'] / 100, 2, '.', ''),
                    $point['previous_start'],
                    $point['previous_end'],
                    number_format($point['previous_cents'] / 100, 2, '.', ''),
                ])->all())
            ->values()
            ->all();

        $this->assertSame($expectedRows, $export['rows']);
        $this->assertSame('current_period_start', $export['headers'][2]);
        $this->assertSame('previous_period_start', $export['headers'][5]);

        foreach ($export['rows'] as $row) {
            foreach ([2, 3, 5, 6] as $dateColumn) {
                $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $row[$dateColumn]);
            }
        }
    }

    public function test_report_routes_require_admin_authentication_and_validate_custom_dates(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
        $this->get(route('admin.reports.export', ['report' => 'summary']))->assertRedirect(route('admin.login'));

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->from(route('admin.dashboard'))->get(route('admin.dashboard', [
            'period' => 'custom',
            'from' => '2026-08-06',
            'to' => '2026-08-01',
        ]))->assertRedirect(route('admin.dashboard'))
            ->assertSessionHasErrors(['to']);
    }

    public function test_empty_successful_queries_are_distinct_from_unavailable_modules(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAsAdmin($admin);

        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('USD 0.00')
            ->assertSee('No funnel events captured yet')
            ->assertSee('No completed or refunded products in this range')
            ->assertSee('No music or video plays captured in this range')
            ->assertDontSee('Sales data could not be loaded');
    }

    public function test_legacy_completed_orders_are_reported_as_partial_coverage_without_fabricating_capture_timestamps(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'LEGACY-COMPLETED',
            'product_key' => 'legacy-product',
            'amount_cents' => 1_000,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => null,
            'created_at' => now()->subDay(),
        ]);

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => '30d']));
        $dashboard = app(ReportDashboardService::class)->dashboard($period);

        $this->assertSame('partial', $dashboard['commerce_coverage']['status']);
        $this->assertSame(1, $dashboard['commerce_coverage']['legacy_orders']);
        $this->assertNull(Order::query()->where('provider_order_id', 'LEGACY-COMPLETED')->value('completed_at'));

        $this->actingAsAdmin($admin);
        $this->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Partial sales coverage')
            ->assertSee('no exact capture timestamp exists');
    }

    public function test_report_query_count_stays_bounded_as_show_rows_increase(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:30:00', 'America/Panama'));
        $user = User::factory()->create(['created_at' => now()->subYear()]);

        for ($index = 1; $index <= 15; $index++) {
            $show = FanEvent::forceCreate([
                'title' => "Scale Show {$index}",
                'timezone' => 'America/Panama',
                'starts_at' => now()->subDay(),
                'status' => 'scheduled',
            ]);
            $order = $this->order("SCALE-{$index}", 1_000 + $index, 'USD', '2026-08-05 12:00:00');
            $order->forceFill(['user_id' => $user->id])->save();
            $this->ticket($user, $show, $order, '2026-08-05 12:00:00');
        }

        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => '30d']));
        DB::flushQueryLog();
        DB::enableQueryLog();
        $dashboard = app(ReportDashboardService::class)->dashboard($period);
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(15, $dashboard['shows']);
        $this->assertLessThanOrEqual(16, $queryCount, "Report executed {$queryCount} queries.");
    }

    public function test_csv_export_fails_closed_when_a_required_module_is_unavailable(): void
    {
        $period = ReportPeriod::fromRequest(Request::create('/', 'GET', ['period' => '30d']));
        $reports = new class extends ReportDashboardService
        {
            public function dashboard(ReportPeriod $period, string $productSort = 'sales'): array
            {
                return ['module_errors' => ['commerce' => true]];
            }
        };

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('commerce report is currently unavailable');

        $reports->export('sales', $period);
    }

    public function test_analytics_endpoint_persists_allowlisted_events_with_anonymous_session_hash_and_idempotency(): void
    {
        $eventId = '018f6f50-3c90-7e25-bf42-123456789abc';
        $sessionId = '018f6f50-3c90-7e25-bf42-abcdef123456';
        $payload = [
            'name' => 'music_play_started',
            'schema_version' => 1,
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'payload' => [
                'screen' => 'music',
                'path' => '/music',
                'item_type' => 'track',
                'item_id' => 'track-one',
                'item_label' => 'Track One',
                'result' => 'started',
            ],
            'timestamp' => now()->toIso8601String(),
        ];

        $this->postJson(route('analytics.events.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('created', true);
        $this->postJson(route('analytics.events.store'), $payload)
            ->assertOk()
            ->assertJsonPath('created', false);

        $this->assertDatabaseCount('access_events', 1);
        $event = AccessEvent::firstOrFail();
        $this->assertSame('music', $event->resource_type);
        $this->assertSame('track-one', $event->resource_key);
        $this->assertSame(hash_hmac('sha256', $sessionId, config('app.key')), $event->session_key);
        $this->assertNotSame($sessionId, $event->session_key);
        $this->assertArrayNotHasKey('session_id', $event->metadata);
        $this->assertArrayNotHasKey('event_id', $event->metadata);
    }

    public function test_checkout_diagnostic_events_are_separate_and_idempotent(): void
    {
        $sessionId = '018f6f50-3c90-7e25-bf42-abcdef123456';
        $events = [
            'store_payment_started',
            'store_payment_failed',
            'store_payment_canceled',
            'store_payment_unavailable',
            'store_checkout_validation_failed',
        ];

        foreach ($events as $index => $name) {
            $payload = [
                'name' => $name,
                'schema_version' => 1,
                'event_id' => sprintf('018f6f50-3c90-7e25-bf42-%012d', $index + 1),
                'session_id' => $sessionId,
                'payload' => [
                    'screen' => 'store_checkout',
                    'path' => '/store/checkout/album',
                    'item_id' => 'paypal',
                    'method' => 'paypal',
                    'checkout_state' => str($name)->after('store_')->toString(),
                    'result' => 'diagnostic',
                ],
            ];

            $this->postJson(route('analytics.events.store'), $payload)->assertCreated();
            $this->postJson(route('analytics.events.store'), $payload)
                ->assertOk()
                ->assertJsonPath('created', false);
        }

        $this->assertDatabaseCount('access_events', count($events));
        $this->assertSame(1, AccessEvent::query()->where('event_name', 'store_payment_failed')->count());
        $this->assertSame(1, AccessEvent::query()->where('event_name', 'store_payment_canceled')->count());
        $this->assertSame(1, AccessEvent::query()->where('event_name', 'store_payment_unavailable')->count());
    }

    public function test_analytics_endpoint_rejects_untracked_events_unexpected_fields_and_oversized_payloads(): void
    {
        $this->postJson(route('analytics.events.store'), [
            'name' => 'paywall_cta_clicked',
            'payload' => ['screen' => 'music', 'path' => '/', 'result' => 'clicked'],
        ])->assertUnprocessable()->assertJsonValidationErrors(['name']);

        $this->postJson(route('analytics.events.store'), [
            'name' => 'store_payment_succeeded',
            'payload' => [
                'screen' => 'store_checkout',
                'path' => '/store/checkout/album',
                'item_type' => 'payment',
                'item_id' => 'paypal',
                'result' => 'succeeded',
                'paypal_order_id' => 'PAYMENT-TOKEN-MUST-NOT-BE-PERSISTED',
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors(['payload']);

        $this->postJson(route('analytics.events.store'), [
            'name' => 'page_view',
            'payload' => [
                'screen' => 'music',
                'path' => '/',
                'title' => str_repeat('A', 3000),
                'result' => 'viewed',
            ],
        ])->assertStatus(413);

        $this->assertDatabaseCount('access_events', 0);
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    private function order(
        string $providerOrderId,
        int $amountCents,
        string $currency,
        string $completedAt,
        ?string $refundedAt = null,
        string $status = 'completed',
    ): Order {
        $completed = CarbonImmutable::parse($completedAt, 'America/Panama');

        return Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => $providerOrderId,
            'provider_capture_id' => in_array($status, ['completed', 'refunded'], true) ? 'CAPTURE-'.$providerOrderId : null,
            'product_key' => 'product-'.strtolower($providerOrderId),
            'amount_cents' => $amountCents,
            'currency' => $currency,
            'status' => $refundedAt ? 'refunded' : $status,
            'completed_at' => in_array($status, ['completed', 'refunded'], true) ? $completed : null,
            'refunded_at' => $refundedAt ? CarbonImmutable::parse($refundedAt, 'America/Panama') : null,
            'metadata' => [
                'product' => [
                    'title' => str($providerOrderId)->headline()->toString(),
                    'kind' => 'product',
                ],
            ],
            'created_at' => $completed,
        ]);
    }

    private function analytics(
        string $name,
        string $resourceType,
        string $resourceKey,
        string $session,
        string $createdAt,
        ?string $label = null,
    ): AccessEvent {
        return AccessEvent::forceCreate([
            'event_name' => $name,
            'schema_version' => 1,
            'resource_type' => $resourceType,
            'resource_key' => $resourceKey,
            'session_key' => hash('sha256', $session),
            'metadata' => array_filter(['item_label' => $label]),
            'created_at' => CarbonImmutable::parse($createdAt, 'America/Panama'),
        ]);
    }

    private function ticket(
        User $user,
        FanEvent $show,
        ?Order $order,
        string $purchasedAt,
        ?string $checkedInAt = null,
    ): Ticket {
        return Ticket::forceCreate([
            'user_id' => $user->id,
            'event_id' => $show->id,
            'order_id' => $order?->id,
            'ticket_code_hash' => hash('sha256', Str::uuid()->toString()),
            'holder_name' => $user->name,
            'status' => $checkedInAt ? 'checked_in' : ($order ? 'confirmed' : 'reserved'),
            'rsvp_status' => 'confirmed',
            'purchased_at' => CarbonImmutable::parse($purchasedAt, 'America/Panama'),
            'checked_in_at' => $checkedInAt ? CarbonImmutable::parse($checkedInAt, 'America/Panama') : null,
            'created_at' => CarbonImmutable::parse($purchasedAt, 'America/Panama'),
        ]);
    }
}
