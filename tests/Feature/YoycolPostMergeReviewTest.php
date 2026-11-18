<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\YoycolFulfillment;
use App\Services\Yoycol\YoycolFulfillmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class YoycolPostMergeReviewTest extends TestCase
{
    use RefreshDatabase;

    private const API = 'https://www.yoycol.com/api/2025/open/v4/';

    /** @var list<array{method: string, url: string}> */
    private array $attemptedRequests = [];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'yoycol.enabled' => true, 'yoycol.sales_enabled' => true,
            'yoycol.access_key' => 'qa-access',
            'yoycol.secret_key' => 'qa-secret',
            'admin.cms_enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    public static function refunds(): array
    {
        return [
            'full refund' => [['status' => 'refunded', 'refund_amount_cents' => 6800]],
            'partial refund' => [['status' => 'completed', 'refund_amount_cents' => 500]],
        ];
    }

    public static function duplicatePages(): array
    {
        return [
            'same page' => [false, [1]],
            'different pages' => [true, [1, 2]],
        ];
    }

    #[DataProvider('refunds')]
    public function test_refunded_ambiguous_order_can_be_reconciled_read_only(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund);
        $this->fakeSupplier($fulfillment);
        $this->loginAdmin();

        $this->post(route('admin.yoycol.reconcile', $fulfillment))
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertLookupPages([1, 2]);
        $this->assertReadOnlyRecovery($fulfillment);
        $this->assertRefundUnchanged($fulfillment, $refund);
    }

    public function test_unrefunded_ambiguous_order_is_reconciled_without_creation(): void
    {
        $fulfillment = $this->fulfillment();
        $this->fakeSupplier($fulfillment);

        app(YoycolFulfillmentService::class)->process($fulfillment);

        $this->assertLookupPages([1, 2]);
        Http::assertSent(fn (Request $request) => $request->method() === 'GET'
            && $request->url() === self::API.'orders/900');
        $this->assertNoWriteAttempts();
        $this->assertSame('900', $fulfillment->fresh()->provider_order_id);
        $this->assertSame('payment_required', $fulfillment->fresh()->state);
    }

    #[DataProvider('duplicatePages')]
    public function test_duplicate_remote_references_do_not_attach_or_resubmit(bool $splitPages, array $expectedPages): void
    {
        // Start in a different state so a no-op cannot satisfy the result.
        $fulfillment = $this->fulfillment([], ['state' => 'submitting']);
        $this->fakeDuplicates($fulfillment, $splitPages);

        app(YoycolFulfillmentService::class)->process($fulfillment);

        $this->assertLookupPages($expectedPages);
        Http::assertSentCount(count($expectedPages));
        $this->assertNoWriteAttempts();
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertSame('review', $fulfillment->fresh()->state);
        $this->assertNotNull($fulfillment->fresh()->last_error);
    }

    #[DataProvider('duplicatePages')]
    public function test_retry_reports_duplicate_matches_instead_of_a_lookup_failure(bool $splitPages, array $expectedPages): void
    {
        $fulfillment = $this->fulfillment();
        $this->fakeDuplicates($fulfillment, $splitPages);

        try {
            app(YoycolFulfillmentService::class)->retry($fulfillment);
            $this->fail('Duplicate supplier references must reject retry.');
        } catch (ValidationException $exception) {
            // process() catches all lookup errors; retry exposes the actual cause.
            $this->assertSame(['order' => ['Multiple supplier orders require review.']], $exception->errors());
        }

        $this->assertLookupPages($expectedPages);
        Http::assertSentCount(count($expectedPages));
        $this->assertNoWriteAttempts();
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertNotNull($fulfillment->fresh()->attempted_at);
    }

    #[DataProvider('refunds')]
    public function test_refund_before_any_submission_never_creates_a_supplier_order(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund, ['attempted_at' => null, 'state' => 'awaiting_capture']);
        $this->fakeSupplier($fulfillment);

        app(YoycolFulfillmentService::class)->process($fulfillment);
        app(YoycolFulfillmentService::class)->process($fulfillment);

        Http::assertNothingSent();
        $this->assertSame([], $this->attemptedRequests, 'An unsubmitted refunded order must not attempt any supplier request.');
        $this->assertSame('refund_review', $fulfillment->fresh()->state);
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertNull($fulfillment->fresh()->attempted_at);
        $this->assertRefundUnchanged($fulfillment, $refund);
    }

    #[DataProvider('refunds')]
    public function test_repeated_reconciliation_refreshes_known_order_without_clearing_refund_review(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund, ['provider_order_id' => '900', 'state' => 'refund_review']);
        $summary = [...$this->summary($fulfillment), 'canPay' => false, 'paidAt' => now()->toIso8601String()];
        $this->fakeSupplier($fulfillment, summary: $summary, tracking: ['waybillCode' => 'QA-TRACK-900']);

        for ($run = 1; $run <= 2; $run++) {
            app(YoycolFulfillmentService::class)->process($fulfillment->fresh());

            Http::assertSentCount($run * 2);
            $this->assertReadOnlyRecovery($fulfillment);
            $this->assertSame($summary['paidAt'], $fulfillment->fresh()->provider_summary['paidAt']);
            $this->assertSame('QA-TRACK-900', $fulfillment->fresh()->tracking['waybillCode']);
            $this->assertRefundUnchanged($fulfillment, $refund);
        }
        $this->assertLookupPages([]);
    }

    #[DataProvider('refunds')]
    public function test_scheduled_sync_recovers_refund_review_and_refreshes_it_on_the_next_due_run(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund, ['state' => 'refund_review']);
        $this->fakeSupplier($fulfillment);

        $this->artisan('yoycol:sync')->assertSuccessful();

        $this->assertLookupPages([1, 2]);
        $this->assertReadOnlyRecovery($fulfillment);
        $this->assertNotNull($fulfillment->fresh()->synced_at);
        $firstSync = $fulfillment->fresh()->synced_at;
        $detailRequests = Http::recorded(fn (Request $request) => $request->url() === self::API.'orders/900')->count();

        $this->travel(16)->minutes();
        $this->artisan('yoycol:sync')->assertSuccessful();

        $this->assertReadOnlyRecovery($fulfillment);
        $this->assertGreaterThan($detailRequests, Http::recorded(fn (Request $request) => $request->url() === self::API.'orders/900')->count());
        $this->assertTrue($fulfillment->fresh()->synced_at->gt($firstSync));
        $this->assertRefundUnchanged($fulfillment, $refund);
    }

    #[DataProvider('refunds')]
    public function test_admin_can_explicitly_cancel_after_recovering_a_refunded_order(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund);
        $this->fakeSupplier($fulfillment);
        $this->loginAdmin();

        $this->post(route('admin.yoycol.reconcile', $fulfillment))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNoWriteAttempts();

        // Exercise the cancellation endpoint even if reconciliation did no work.
        $this->post(route('admin.yoycol.cancel', $fulfillment), ['confirmed_cancel' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertLookupPages([1, 2]);
        $this->assertCancellation($fulfillment, $refund);
    }

    #[DataProvider('refunds')]
    public function test_explicit_cancellation_of_an_already_known_refunded_order_preserves_customer_payment(array $refund): void
    {
        // Control: cancellation itself can succeed once the ID is known.
        $fulfillment = $this->fulfillment($refund, ['provider_order_id' => '900', 'state' => 'refund_review']);
        $this->fakeSupplier($fulfillment);
        $this->loginAdmin();

        $this->post(route('admin.yoycol.cancel', $fulfillment), ['confirmed_cancel' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();

        Http::assertSentInOrder([
            fn (Request $request) => $request->method() === 'GET' && $request->url() === self::API.'orders/900',
            fn (Request $request) => $request->method() === 'PUT' && $request->url() === self::API.'orders/900/cancel',
        ]);
        Http::assertSentCount(2);
        $this->assertCancellation($fulfillment, $refund);
    }

    #[DataProvider('refunds')]
    public function test_missing_remote_order_stays_in_refund_review_without_resubmission(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund);
        $this->fakeSupplier($fulfillment, pages: [[]]);

        app(YoycolFulfillmentService::class)->process($fulfillment);
        app(YoycolFulfillmentService::class)->process($fulfillment->fresh());

        $this->assertLookupPages([1, 1]);
        Http::assertSentCount(2);
        $this->assertNoWriteAttempts();
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertSame('refund_review', $fulfillment->fresh()->state);
        $this->assertNotNull($fulfillment->fresh()->attempted_at);
    }

    #[DataProvider('refunds')]
    public function test_lookup_failure_keeps_refund_review_and_does_not_resubmit(array $refund): void
    {
        $fulfillment = $this->fulfillment($refund, ['state' => 'refund_review']);
        $this->fakeResponses(['GET '.self::API.'orders?page=1&size=50' => Http::response([], 503)]);

        app(YoycolFulfillmentService::class)->process($fulfillment);

        $this->assertLookupPages([1]);
        Http::assertSentCount(1);
        $this->assertNoWriteAttempts();
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertSame('refund_review', $fulfillment->fresh()->state);
        $this->assertNotNull($fulfillment->fresh()->last_error);
    }

    private function loginAdmin(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))
            ->withSession(['admin_authenticated_at' => now()->timestamp]);
    }

    private function assertReadOnlyRecovery(YoycolFulfillment $fulfillment): void
    {
        Http::assertSent(fn (Request $request) => $request->method() === 'GET' && $request->url() === self::API.'orders/900');
        $this->assertNoWriteAttempts();
        $this->assertSame('900', $fulfillment->fresh()->provider_order_id);
        $this->assertSame('refund_review', $fulfillment->fresh()->state);
    }

    private function assertLookupPages(array $pages): void
    {
        $requests = Http::recorded(fn (Request $request) => parse_url($request->url(), PHP_URL_PATH) === '/api/2025/open/v4/orders');
        $actual = $requests->map(function (array $pair): int {
            [$request] = $pair;
            $this->assertSame('GET', $request->method());
            $this->assertSame('www.yoycol.com', parse_url($request->url(), PHP_URL_HOST));
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
            $this->assertSame('50', $query['size'] ?? null);

            return (int) ($query['page'] ?? 0);
        })->all();
        $this->assertSame($pages, $actual, 'Expected supplier lookup pages must actually be requested.');
    }

    private function assertCancellation(YoycolFulfillment $fulfillment, array $refund): void
    {
        $this->assertSame([
            ['method' => 'PUT', 'url' => self::API.'orders/900/cancel'],
        ], $this->writeAttempts(), 'Only one explicit supplier cancellation may be attempted.');
        $writes = Http::recorded(fn (Request $request) => $request->method() !== 'GET');
        $this->assertCount(1, $writes);
        $this->assertSame('PUT', $writes->first()[0]->method());
        $this->assertSame(self::API.'orders/900/cancel', $writes->first()[0]->url());
        $this->assertSame('900', $fulfillment->fresh()->provider_order_id);
        $this->assertSame('cancelled', $fulfillment->fresh()->state);
        $this->assertRefundUnchanged($fulfillment, $refund);
    }

    private function assertNoWriteAttempts(): void
    {
        $this->assertSame([], $this->writeAttempts(), 'Read-only recovery must not attempt any supplier write, even if its exception is caught.');
    }

    private function writeAttempts(): array
    {
        return array_values(array_filter($this->attemptedRequests, fn (array $request) => $request['method'] !== 'GET'));
    }

    private function assertRefundUnchanged(YoycolFulfillment $fulfillment, array $refund): void
    {
        $order = $fulfillment->order->fresh();
        $this->assertSame($refund['status'], $order->status);
        $this->assertSame($refund['refund_amount_cents'], $order->refund_amount_cents);
        $this->assertSame('QA-CAPTURE-1', $order->provider_capture_id);
        $this->assertSame(6800, $order->amount_cents);
    }

    private function fulfillment(array $orderAttributes = [], array $attributes = []): YoycolFulfillment
    {
        $order = Order::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'paypal', 'provider_order_id' => 'QA-PAID-1-yoycol-1',
            'provider_capture_id' => 'QA-CAPTURE-1', 'product_key' => 'yoycol-1',
            'amount_cents' => 6800, 'currency' => 'USD', 'status' => 'completed',
            ...$orderAttributes,
        ]);

        return YoycolFulfillment::create([
            'order_id' => $order->id, 'store_order_sn' => 'RENY-QA-AMBIGUOUS',
            'state' => 'review', 'attempted_at' => now()->subMinutes(2),
            'request_payload' => ['storeOrderSn' => 'RENY-QA-AMBIGUOUS'],
            ...$attributes,
        ]);
    }

    private function summary(YoycolFulfillment $fulfillment): array
    {
        return [
            'orderId' => 900, 'orderNo' => 'Y900', 'storeOrderSn' => $fulfillment->store_order_sn,
            'canPay' => true, 'canCancel' => true, 'status' => 'unpaid', 'currency' => 'USD',
        ];
    }

    private function fakeDuplicates(YoycolFulfillment $fulfillment, bool $splitPages): void
    {
        $first = $this->summary($fulfillment);
        $second = [...$first, 'orderId' => 901, 'orderNo' => 'Y901'];
        $pages = $splitPages ? [[$first], [$second], []] : [[$first, $second], []];
        $this->fakeSupplier($fulfillment, pages: $pages);
    }

    private function fakeSupplier(YoycolFulfillment $fulfillment, ?array $pages = null, ?array $summary = null, array $tracking = []): void
    {
        $summary ??= $this->summary($fulfillment);
        $pages ??= [[$summary], []];
        // Finite pages: an unexpected extra request is rejected, not repeated forever.
        $responses = [];
        foreach ($pages as $index => $records) {
            $responses['GET '.self::API.'orders?page='.($index + 1).'&size=50'] = Http::response(['code' => '100000', 'data' => ['records' => $records]]);
        }
        $responses['GET '.self::API.'orders/900'] = Http::response(['code' => '100000', 'data' => ['order' => $summary]]);
        $responses['GET '.self::API.'orders/900/tracking'] = Http::response(['code' => '100000', 'data' => $tracking]);
        $responses['PUT '.self::API.'orders/900/cancel'] = Http::response(['code' => '100000', 'data' => 'success']);
        $this->fakeResponses($responses);
    }

    private function fakeResponses(array $responses): void
    {
        Http::fake(function (Request $request) use ($responses) {
            // Record before matching: the application can catch a rejected fake's
            // exception before Laravel records a completed request/response pair.
            $this->attemptedRequests[] = ['method' => $request->method(), 'url' => $request->url()];
            $key = $request->method().' '.$request->url();

            return $responses[$key] ?? throw new \RuntimeException('Unexpected supplier request: '.$key);
        });
    }
}
