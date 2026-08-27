<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Rsvp;
use App\Models\Ticket;
use App\Models\User;
use App\Reports\DashboardReportService;
use App\Reports\ReportRange;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardReportingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-08-06 07:44:00', 'America/Panama'));
        config(['admin.publishing_timezone' => 'America/Panama']);
    }

    public function test_funnel_deduplicates_sessions_uses_canonical_purchases_and_labels_coverage(): void
    {
        $this->event('page_view', 'session-a', 'page', 'store', '2026-08-02 09:00:00');
        $this->event('store_product_opened', 'session-a', 'product', 'vinyl', '2026-08-02 09:01:00');
        $this->event('store_checkout_started', 'session-a', 'checkout', 'bag', '2026-08-02 09:02:00');
        $this->event('store_checkout_started', 'session-a', 'checkout', 'bag', '2026-08-02 09:03:00');
        $this->event('store_payment_failed', 'session-a', 'payment_method', 'paypal', '2026-08-02 09:04:00');

        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-COMPLETE',
            'provider_capture_id' => 'CAPTURE-COMPLETE',
            'product_key' => 'vinyl',
            'amount_cents' => 2500,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-08-02 09:05:00'),
            'created_at' => $this->utc('2026-08-02 09:00:00'),
            'metadata' => ['checkout' => ['analytics_session_id' => 'session-a']],
        ]);

        $funnel = $this->service('2026-08-01', '2026-08-06')->funnel();

        $this->assertSame(1, $funnel['steps'][0]['current']['sessions']);
        $this->assertSame(2, $funnel['steps'][0]['current']['events']);
        $this->assertSame(1, $funnel['steps'][1]['current']['sessions']);
        $this->assertSame(2, $funnel['steps'][1]['current']['events']);
        $this->assertSame(1, $funnel['steps'][2]['current']['sessions']);
        $this->assertSame(100.0, $funnel['steps'][1]['conversion']);
        $this->assertSame(100.0, $funnel['steps'][2]['conversion']);
        $this->assertSame(['sessions' => 1, 'events' => 1], $funnel['failed']);
        $this->assertTrue($funnel['purchase_linkage']['current']['complete']);
        $this->assertSame(0, $funnel['purchase_linkage']['current']['unlinked_transactions']);
        $this->assertSame('2026-08-02', $funnel['available_from']);
        $this->assertTrue($funnel['coverage_partial']);
    }

    public function test_funnel_marks_unlinked_canonical_purchases_incomparable_in_ui_and_csv(): void
    {
        $this->event('page_view', 'session-linked', 'page', 'store', '2026-08-01 09:00:00');
        $this->event('store_checkout_started', 'session-linked', 'checkout', 'bag', '2026-08-01 09:01:00');

        foreach ([
            ['capture' => 'CAPTURE-LINKED', 'session' => 'session-linked'],
            ['capture' => 'CAPTURE-NO-SESSION', 'session' => null],
            ['capture' => 'CAPTURE-OTHER-SESSION', 'session' => 'session-without-checkout'],
        ] as $index => $fixture) {
            Order::forceCreate([
                'provider' => 'paypal',
                'provider_order_id' => 'ORDER-FUNNEL-'.($index + 1),
                'provider_capture_id' => $fixture['capture'],
                'product_key' => 'product-'.($index + 1),
                'amount_cents' => 2500,
                'currency' => 'USD',
                'status' => 'completed',
                'completed_at' => $this->utc('2026-08-01 09:05:00')->addMinutes($index),
                'created_at' => $this->utc('2026-08-01 09:04:00')->addMinutes($index),
                'metadata' => $fixture['session']
                    ? ['checkout' => ['analytics_session_id' => $fixture['session']]]
                    : [],
            ]);
        }

        $funnel = $this->service('2026-08-01', '2026-08-06')->funnel();
        $purchase = collect($funnel['steps'])->firstWhere('key', 'purchase');

        $this->assertSame(1, $purchase['current']['sessions']);
        $this->assertSame(3, $purchase['current']['events']);
        $this->assertNull($purchase['conversion']);
        $this->assertSame('incomparable_sessions', $purchase['conversion_reason']);
        $this->assertFalse($funnel['purchase_linkage']['current']['complete']);
        $this->assertSame(1, $funnel['purchase_linkage']['current']['linked_transactions']);
        $this->assertSame(2, $funnel['purchase_linkage']['current']['unlinked_transactions']);
        $this->assertTrue($funnel['coverage_partial']);
        $this->assertStringContainsString('2 transacciones del rango actual', $funnel['coverage_message']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);

        $response = $this->get(route('admin.reports.export', [
            'report' => 'funnel',
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-06',
        ]));

        $response->assertOk();
        $lines = preg_split('/\r\n|\n|\r/', trim($response->streamedContent()));
        $headers = str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"));
        $purchaseRow = collect(array_slice($lines, 1))
            ->map(fn (string $line): array => array_combine($headers, str_getcsv($line)))
            ->firstWhere('step', 'purchase');

        $this->assertSame('1', $purchaseRow['sessions']);
        $this->assertSame('3', $purchaseRow['events']);
        $this->assertSame('', $purchaseRow['conversion_percent']);
        $this->assertSame('incomparable_sessions', $purchaseRow['conversion_reason']);
        $this->assertSame('2', $purchaseRow['unlinked_purchase_transactions']);
    }

    public function test_empty_bag_open_is_excluded_from_funnel_ui_and_csv(): void
    {
        $this->event('page_view', 'session-opened', 'page', 'store', '2026-08-02 09:00:00');
        $this->event('store_checkout_started', 'session-opened', 'checkout', 'bag', '2026-08-02 09:01:00', result: 'opened');
        $this->event('store_checkout_started', 'session-empty', 'checkout', 'bag', '2026-08-02 09:02:00', result: 'empty');

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);
        $range = [
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-06',
        ];

        $dashboard = $this->get(route('admin.dashboard', $range));
        $dashboard->assertOk();
        $reports = $dashboard->viewData('reports');
        $checkout = collect($reports['funnel']['data']['steps'])->firstWhere('key', 'checkout');

        $this->assertSame(1, $checkout['current']['sessions']);
        $this->assertSame(1, $checkout['current']['events']);
        $dashboard->assertSeeInOrder([
            'Checkout iniciado',
            '<strong>1</strong>',
            '<small>1 eventos · anterior 0 sesiones</small>',
        ], false);

        $csvResponse = $this->get(route('admin.reports.export', [
            'report' => 'funnel',
            ...$range,
        ]));
        $csvResponse->assertOk();
        $lines = preg_split('/\r\n|\n|\r/', trim($csvResponse->streamedContent()));
        $headers = str_getcsv(ltrim($lines[0], "\xEF\xBB\xBF"));
        $checkoutRow = collect(array_slice($lines, 1))
            ->map(fn (string $line): array => array_combine($headers, str_getcsv($line)))
            ->firstWhere('step', 'checkout');

        $this->assertSame((string) $checkout['current']['sessions'], $checkoutRow['sessions']);
        $this->assertSame((string) $checkout['current']['events'], $checkoutRow['events']);
    }

    public function test_content_and_product_rankings_keep_metric_types_sessions_and_currencies_separate(): void
    {
        $this->event('music_play_started', 'music-session', 'song', 'track-1', '2026-08-03 10:00:00', 'Luna');
        $this->event('music_play_started', 'music-session', 'song', 'track-1', '2026-08-03 10:01:00', 'Luna');
        $this->event('video_play_started', 'video-session', 'video', 'video-1', '2026-08-03 10:02:00', 'Capri Live');

        foreach ([6000, 4000] as $index => $amount) {
            Order::forceCreate([
                'provider' => 'paypal',
                'provider_order_id' => 'ORDER-BUNDLE-'.($index + 1),
                'provider_capture_id' => 'CAPTURE-BUNDLE',
                'product_key' => 'bundle',
                'amount_cents' => $amount,
                'currency' => 'USD',
                'status' => 'completed',
                'completed_at' => $this->utc('2026-08-03 11:00:00'),
                'created_at' => $this->utc('2026-08-03 10:55:00'),
                'metadata' => ['product' => ['title' => 'Golden bundle', 'type' => 'merch']],
            ]);
        }
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-EUR',
            'provider_capture_id' => 'CAPTURE-EUR',
            'product_key' => 'bundle',
            'amount_cents' => 5000,
            'currency' => 'EUR',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-08-03 12:00:00'),
            'created_at' => $this->utc('2026-08-03 11:55:00'),
            'metadata' => ['product' => ['title' => 'Golden bundle', 'type' => 'merch']],
        ]);

        $service = $this->service('2026-08-01', '2026-08-06');
        $content = $service->content();
        $products = $service->products();

        $this->assertSame('Luna', $content['rows'][0]['title']);
        $this->assertSame('Reproducciones', $content['rows'][0]['metric']);
        $this->assertSame(2, $content['rows'][0]['interactions']);
        $this->assertSame(1, $content['rows'][0]['sessions']);
        $this->assertTrue($content['coverage_partial']);
        $this->assertFalse($content['coverage_unavailable']);
        $this->assertCount(2, $products);
        $this->assertSame('USD', $products[0]['currency']);
        $this->assertSame(10000, $products[0]['net_cents']);
        $this->assertSame(2, $products[0]['units']);
        $this->assertSame(1, $products[0]['orders']);
        $this->assertSame('EUR', $products[1]['currency']);
    }

    public function test_show_report_uses_rsvp_ticket_and_checkin_sources_without_pii(): void
    {
        $fan = User::factory()->create();
        $show = FanEvent::create([
            'title' => 'Reny Live Panamá',
            'timezone' => 'America/Panama',
            'starts_at' => $this->utc('2026-08-20 20:00:00'),
            'status' => 'scheduled',
            'metadata' => ['store_event_key' => 'reny-live-pa'],
        ]);
        Rsvp::create([
            'event_key' => 'reny-live-pa',
            'event_name' => 'Reny Live Panamá',
            'name' => 'Private Fan',
            'email' => 'private@example.test',
            'country' => 'Panama',
            'metadata' => ['ticket_quantity' => 2],
            'created_at' => $this->utc('2026-08-02 10:00:00'),
        ]);
        Ticket::create([
            'user_id' => $fan->id,
            'event_id' => $show->id,
            'ticket_code_hash' => hash('sha256', 'rsvp'),
            'holder_name' => 'Private RSVP Holder',
            'status' => 'checked_in',
            'rsvp_status' => 'confirmed',
            'purchased_at' => $this->utc('2026-08-02 11:00:00'),
            'checked_in_at' => $this->utc('2026-08-05 18:00:00'),
            'created_at' => $this->utc('2026-08-02 11:00:00'),
        ]);
        $order = Order::forceCreate([
            'user_id' => $fan->id,
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-TICKET',
            'provider_capture_id' => 'CAPTURE-TICKET',
            'product_key' => 'reny-live-pa',
            'amount_cents' => 3500,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-08-03 12:00:00'),
            'created_at' => $this->utc('2026-08-03 11:55:00'),
        ]);
        Ticket::create([
            'user_id' => $fan->id,
            'event_id' => $show->id,
            'order_id' => $order->id,
            'ticket_code_hash' => hash('sha256', 'purchase'),
            'holder_name' => 'Private Buyer',
            'status' => 'checked_in',
            'rsvp_status' => 'confirmed',
            'purchased_at' => $this->utc('2026-08-03 12:00:00'),
            'checked_in_at' => $this->utc('2026-08-05 19:00:00'),
            'created_at' => $this->utc('2026-08-03 12:00:00'),
        ]);
        $priorOrder = Order::forceCreate([
            'user_id' => $fan->id,
            'provider' => 'paypal',
            'provider_order_id' => 'ORDER-TICKET-PRIOR',
            'provider_capture_id' => 'CAPTURE-TICKET-PRIOR',
            'product_key' => 'reny-live-pa',
            'amount_cents' => 3500,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => $this->utc('2026-07-15 12:00:00'),
            'created_at' => $this->utc('2026-07-15 11:55:00'),
        ]);
        Ticket::create([
            'user_id' => $fan->id,
            'event_id' => $show->id,
            'order_id' => $priorOrder->id,
            'ticket_code_hash' => hash('sha256', 'prior-purchase'),
            'holder_name' => 'Prior Buyer',
            'status' => 'checked_in',
            'rsvp_status' => 'confirmed',
            'purchased_at' => $this->utc('2026-07-15 12:00:00'),
            'checked_in_at' => $this->utc('2026-08-05 19:30:00'),
            'created_at' => $this->utc('2026-07-15 12:00:00'),
        ]);

        $shows = $this->service('2026-08-01', '2026-08-06')->shows();

        $this->assertCount(1, $shows);
        $this->assertSame(3, $shows[0]['rsvps']);
        $this->assertSame(1, $shows[0]['tickets']);
        $this->assertSame(3, $shows[0]['checkins']);
        $this->assertSame(33.3, $shows[0]['rsvp_to_ticket']);
        $this->assertSame(100.0, $shows[0]['ticket_to_checkin']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);
        $response = $this->get(route('admin.reports.export', [
            'report' => 'shows',
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-06',
        ]));

        $response->assertOk()->assertDownload('shows_2026-08-01_2026-08-06.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Reny Live Panamá', $csv);
        $this->assertStringNotContainsString('private@example.test', $csv);
        $this->assertStringNotContainsString('Private Fan', $csv);
        $this->assertStringNotContainsString('Private Buyer', $csv);
        $this->assertStringNotContainsString('Prior Buyer', $csv);
    }

    public function test_content_before_instrumentation_is_unavailable_instead_of_empty(): void
    {
        $this->event('music_play_started', 'coverage-session', 'music', 'coverage-track', '2026-08-06 07:00:00', 'Coverage Track');

        $content = $this->service('2025-01-01', '2025-01-31')->content();

        $this->assertSame([], $content['rows']);
        $this->assertSame('2026-08-06', $content['available_from']);
        $this->assertTrue($content['coverage_unavailable']);
        $this->assertFalse($content['coverage_partial']);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);
        $this->get(route('admin.dashboard', [
            'preset' => 'custom',
            'start' => '2025-01-01',
            'end' => '2025-01-31',
        ]))
            ->assertOk()
            ->assertSee('No disponible: los eventos de contenido se capturan desde 2026-08-06.');
    }

    public function test_content_csv_neutralizes_spreadsheet_formulas(): void
    {
        $this->event('music_play_started', 'formula-session', 'music', 'formula-track', '2026-08-03 10:00:00', '=2+3');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);

        $response = $this->get(route('admin.reports.export', [
            'report' => 'content',
            'preset' => 'custom',
            'start' => '2026-08-01',
            'end' => '2026-08-06',
        ]));

        $response->assertOk();
        $lines = preg_split('/\r\n|\n|\r/', trim($response->streamedContent()));
        $row = str_getcsv($lines[1]);

        $this->assertSame("'=2+3", $row[2]);
    }

    public function test_all_csv_exports_are_admin_only_utf8_and_use_stable_headers(): void
    {
        $fan = User::factory()->create();

        $this->actingAs($fan)
            ->get(route('admin.reports.export', ['report' => 'summary', 'preset' => '30d']))
            ->assertForbidden();

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($admin)->withSession(['admin_authenticated_at' => now()->timestamp]);

        foreach (['summary', 'sales', 'funnel', 'products', 'content', 'shows'] as $report) {
            $response = $this->get(route('admin.reports.export', [
                'report' => $report,
                'preset' => '30d',
            ]));

            $response->assertOk();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $response->streamedContent());
            $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        }
    }

    public function test_orders_without_verified_capture_time_are_excluded_and_mark_coverage_partial(): void
    {
        Order::forceCreate([
            'provider' => 'paypal',
            'provider_order_id' => 'LEGACY-COMPLETED',
            'provider_capture_id' => 'LEGACY-CAPTURE',
            'product_key' => 'legacy-product',
            'amount_cents' => 9900,
            'currency' => 'USD',
            'status' => 'completed',
            'completed_at' => null,
            'created_at' => $this->utc('2026-08-03 10:00:00'),
        ]);

        $modules = $this->service('2026-08-01', '2026-08-06')->modules();

        $this->assertSame(0, $modules['kpis']['data']['sales'][0]['current']);
        $this->assertSame(0, $modules['kpis']['data']['orders']['current']);
        $this->assertSame([], $modules['products']['data']);
        $this->assertSame('partial', $modules['commerce_coverage']['data']['status']);
        $this->assertSame(1, $modules['commerce_coverage']['data']['legacy_orders']);
        $this->assertNull(Order::query()->where('provider_order_id', 'LEGACY-COMPLETED')->value('completed_at'));
    }

    public function test_report_query_count_stays_bounded_as_show_rows_increase(): void
    {
        $fan = User::factory()->create();

        foreach (range(1, 15) as $index) {
            $show = FanEvent::create([
                'title' => 'QA Show '.$index,
                'timezone' => 'America/Panama',
                'starts_at' => $this->utc('2026-08-20 20:00:00')->addDays($index),
                'status' => 'scheduled',
                'metadata' => ['store_event_key' => 'qa-show-'.$index],
            ]);
            $order = Order::forceCreate([
                'user_id' => $fan->id,
                'provider' => 'paypal',
                'provider_order_id' => 'ORDER-QA-'.$index,
                'provider_capture_id' => 'CAPTURE-QA-'.$index,
                'product_key' => 'qa-show-'.$index,
                'amount_cents' => 2500,
                'currency' => 'USD',
                'status' => 'completed',
                'completed_at' => $this->utc('2026-08-05 12:00:00'),
                'created_at' => $this->utc('2026-08-05 11:55:00'),
            ]);
            Ticket::create([
                'user_id' => $fan->id,
                'event_id' => $show->id,
                'order_id' => $order->id,
                'ticket_code_hash' => hash('sha256', 'qa-ticket-'.$index),
                'holder_name' => 'QA Holder',
                'status' => 'confirmed',
                'rsvp_status' => 'confirmed',
                'purchased_at' => $this->utc('2026-08-05 12:00:00'),
                'created_at' => $this->utc('2026-08-05 12:00:00'),
            ]);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        $modules = $this->service('2026-08-01', '2026-08-06')->modules();
        $queryCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(15, $modules['shows']['data']);
        $this->assertLessThanOrEqual(20, $queryCount, "Dashboard executed {$queryCount} queries.");
    }

    public function test_twelve_month_preset_uses_exactly_twelve_rolling_month_buckets(): void
    {
        $request = Request::create('/reports', 'GET', ['preset' => '12m']);
        $range = ReportRange::fromRequest($request);

        $sales = (new DashboardReportService($range))->salesSeries();
        $points = $sales['series'][0]['points'];

        $this->assertSame('month', $sales['granularity']);
        $this->assertCount(12, $points);
        $this->assertSame('2025-08-07', $points[0]['date']);
        $this->assertSame('2026-07-07', $points[11]['date']);
        $this->assertSame('2024-08-07', $points[0]['previous_date']);
        $this->assertSame('2025-07-07', $points[11]['previous_date']);
    }

    private function service(string $start, string $end): DashboardReportService
    {
        $request = Request::create('/reports', 'GET', [
            'preset' => 'custom',
            'start' => $start,
            'end' => $end,
        ]);

        return new DashboardReportService(ReportRange::fromRequest($request));
    }

    private function event(
        string $name,
        string $session,
        string $resourceType,
        string $resourceKey,
        string $localDateTime,
        ?string $label = null,
        string $result = 'succeeded',
    ): AccessEvent {
        return AccessEvent::create([
            'event_name' => $name,
            'schema_version' => 1,
            'occurred_at' => $this->utc($localDateTime),
            'session_id' => $session,
            'idempotency_key' => $name.'-'.$session.'-'.str()->uuid(),
            'resource_type' => $resourceType,
            'resource_key' => $resourceKey,
            'result' => $result,
            'metadata' => array_filter([
                'item_type' => $resourceType,
                'item_id' => $resourceKey,
                'item_label' => $label,
                'result' => $result,
            ]),
        ]);
    }

    private function utc(string $localDateTime): CarbonImmutable
    {
        return CarbonImmutable::parse($localDateTime, 'America/Panama')->utc();
    }
}
