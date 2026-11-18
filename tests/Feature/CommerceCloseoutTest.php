<?php

namespace Tests\Feature;

use App\Mail\CommerceNotice;
use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use App\Models\YoycolFulfillment;
use App\Services\Commerce\CommerceNotifications;
use App\Services\Commerce\PayPalCheckoutFinalizer;
use App\Services\Commerce\PayPalPaymentRecovery;
use App\Services\PayPalService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CommerceCloseoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_merch_cannot_bypass_shipping_through_direct_checkout_or_payment_endpoints(): void
    {
        Http::preventStrayRequests();
        Http::fake();
        $this->get('/merch')->assertOk()->assertDontSee('data-buy="merch"', false);
        $this->get('/store/checkout/merch')->assertNotFound();
        $payload = ['identifier' => 'buyer@example.test', 'customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.test', 'customer_country' => 'Panama', 'product_keys' => ['merch']];
        $this->postJson('/checkout/paypal/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('product_keys');
        $this->postJson('/checkout/local', [...$payload, 'local_reference' => 'BANK-123456'])->assertUnprocessable();
        Http::assertNothingSent();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_general_recovery_completes_a_multi_item_checkout_once_using_its_full_total(): void
    {
        $first = $this->order(['status' => 'payment_review']);
        $second = $this->order(['user_id' => $first->user_id, 'provider_order_id' => 'PP-RECOVERY-2-singles', 'product_key' => 'singles', 'amount_cents' => 800]);
        $this->mock(PayPalService::class)->shouldReceive('confirmedOrder')->once()->with('PP-RECOVERY', 3200, 'USD')
            ->andReturn(['order_id' => 'PP-RECOVERY', 'capture_id' => 'CAPTURE-RECOVERED', 'payload' => []]);
        $recovery = app(PayPalPaymentRecovery::class);
        $recovery->recover($first);
        $recovery->recover($second);
        $recovery->recover($first);
        $this->assertSame('completed', $first->fresh()->status);
        $this->assertSame('completed', $second->fresh()->status);
        $this->assertSame(2, AccessEvent::where('event_name', 'purchase')->count());
        $this->assertSame('CAPTURE-RECOVERED', $second->fresh()->provider_capture_id);
    }

    public function test_scheduler_recovers_non_yoycol_payment_even_when_supplier_is_disabled(): void
    {
        config(['yoycol.enabled' => false]);
        $order = $this->order();
        $this->travel(2)->minutes();
        $this->mock(PayPalService::class)->shouldReceive('confirmedOrder')->once()->with('PP-RECOVERY', 2400, 'USD')
            ->andReturn(['order_id' => 'PP-RECOVERY', 'capture_id' => 'CAPTURE-SCHEDULED']);
        $this->artisan('paypal:reconcile')->assertSuccessful();
        $this->assertSame('completed', $order->fresh()->status);
    }

    public function test_recovery_never_reactivates_a_partially_refunded_payment(): void
    {
        $order = $this->order(['status' => 'payment_review', 'refund_amount_cents' => 100]);
        $this->mock(PayPalService::class)->shouldNotReceive('confirmedOrder');
        app(PayPalPaymentRecovery::class)->recover($order);
        $this->assertSame('payment_review', $order->fresh()->status);
        $this->assertDatabaseCount('access_events', 0);
        $this->expectException(ValidationException::class);
        app(PayPalCheckoutFinalizer::class)->finalize(collect([$order]), ['order_id' => 'PP-RECOVERY', 'capture_id' => 'CAPTURE']);
    }

    public function test_all_products_persist_capture_intent_and_reject_a_second_capture_attempt(): void
    {
        $order = $this->order(['metadata' => ['checkout' => ['paypal_order_id' => 'PP-RECOVERY']]]);
        $finalizer = app(PayPalCheckoutFinalizer::class);
        $finalizer->beginCapture(collect([$order]));
        $this->assertTrue(data_get($order->fresh()->metadata, 'checkout.capture_started'));
        $this->expectException(ValidationException::class);
        $finalizer->beginCapture(collect([$order]));
    }

    public function test_receipt_tracking_and_daily_supplier_reminders_are_deduplicated(): void
    {
        $this->enableMail();
        Mail::fake();
        $order = $this->order(['status' => 'completed']);
        $fulfillment = YoycolFulfillment::create(['order_id' => $order->id, 'store_order_sn' => 'RENY-TEST', 'state' => 'payment_required', 'request_payload' => []]);
        $notices = app(CommerceNotifications::class);
        $notices->send($order->fresh());
        $notices->send($order->fresh());
        Mail::assertSentCount(2);
        Mail::assertSent(CommerceNotice::class, fn ($mail) => $mail->hasTo('operations@example.test') && str_contains($mail->bodyText, 'paga al proveedor'));
        $this->travel(1)->days();
        $notices->send($order->fresh());
        Mail::assertSentCount(3);
        $fulfillment->update(['state' => 'shipped', 'tracking' => ['waybillCode' => 'TEST-TRACKING']]);
        $notices->send($order->fresh());
        $notices->send($order->fresh());
        Mail::assertSentCount(4);
        $this->assertSame(4, DB::table('commerce_notices')->whereNotNull('sent_at')->count());
    }

    public function test_refund_suppresses_supplier_payment_reminder_even_before_reconciliation(): void
    {
        $this->enableMail();
        Mail::fake();
        $order = $this->order(['status' => 'refunded', 'refund_amount_cents' => 2400]);
        YoycolFulfillment::create(['order_id' => $order->id, 'store_order_sn' => 'RENY-REFUND', 'state' => 'payment_required', 'request_payload' => []]);
        app(CommerceNotifications::class)->send($order);
        Mail::assertSentCount(2);
        Mail::assertSent(CommerceNotice::class, fn ($mail) => $mail->hasTo('operations@example.test') && str_contains($mail->bodyText, 'No pagues producción'));
        Mail::assertNotSent(CommerceNotice::class, fn ($mail) => str_contains($mail->bodyText, 'paga al proveedor'));
    }

    public function test_disabled_or_historical_notifications_do_not_send_anything(): void
    {
        Mail::fake();
        $order = $this->order(['status' => 'completed']);
        $order->forceFill(['created_at' => now()->subDays(3)])->save();
        app(CommerceNotifications::class)->send($order);
        $this->enableMail();
        app(CommerceNotifications::class)->send($order);
        Mail::assertNothingSent();
        $this->assertDatabaseCount('commerce_notices', 0);
    }

    public function test_notice_template_renders_without_customer_address_or_payment_data(): void
    {
        $html = (new CommerceNotice('Pedido RENY-1', 'Tu pedido fue enviado.', 'https://example.test/account'))->render();
        $this->assertStringContainsString('Tu pedido fue enviado.', $html);
    }

    public function test_failed_email_delivery_remains_retryable(): void
    {
        $this->enableMail();
        $order = $this->order(['status' => 'completed']);
        Mail::shouldReceive('getDefaultDriver')->andReturn('array');
        Mail::shouldReceive('to')->once()->andReturnSelf();
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Transport unavailable'));
        app(CommerceNotifications::class)->send($order);
        $this->assertSame(1, DB::table('commerce_notices')->whereNull('sent_at')->count());
        Mail::fake();
        app(CommerceNotifications::class)->send($order);
        Mail::assertSentCount(1);
        $this->assertSame(1, DB::table('commerce_notices')->whereNotNull('sent_at')->count());
    }

    public function test_readiness_reports_missing_configuration_without_printing_secrets(): void
    {
        config(['services.paypal.client_secret' => 'must-never-be-printed', 'commerce.notifications_enabled' => false]);
        $this->artisan('commerce:readiness', ['--sandbox' => true])
            ->expectsOutput('FAIL Transactional notifications enabled with activation date')
            ->doesntExpectOutputToContain('must-never-be-printed')
            ->assertFailed();
    }

    public function test_refund_persistence_and_access_changes_roll_back_together_on_failure(): void
    {
        $order = $this->order(['status' => 'completed', 'provider_capture_id' => 'CAPTURE-REFUND']);
        $this->mock(PayPalService::class)->shouldReceive('verifyWebhook')->twice()->andReturnTrue();
        $this->mock(UserHubPurchaseSync::class)->shouldReceive('recordRefund')->once()
            ->andThrow(new \RuntimeException('Temporary persistence failure'));
        $payload = ['event_type' => 'PAYMENT.CAPTURE.REFUNDED', 'resource' => [
            'id' => 'REFUND-ATOMIC', 'amount' => ['value' => '24.00', 'currency_code' => 'USD'],
            'supplementary_data' => ['related_ids' => ['capture_id' => 'CAPTURE-REFUND']],
        ]];
        $this->postJson('/paypal/refund', $payload)->assertStatus(500);
        $this->assertDatabaseCount('order_refunds', 0);
        $this->assertSame('completed', $order->fresh()->status);
        $this->app->forgetInstance(UserHubPurchaseSync::class);
        $this->postJson('/paypal/refund', $payload)->assertOk()->assertJsonPath('refunded_orders', 1);
        $this->assertDatabaseCount('order_refunds', 1);
        $this->assertSame('refunded', $order->fresh()->status);
    }

    private function enableMail(): void
    {
        config(['commerce.notifications_enabled' => true, 'commerce.notifications_since' => now()->subHour()->toIso8601String(), 'commerce.operations_email' => 'operations@example.test']);
    }

    private function order(array $attributes = []): Order
    {
        return Order::create([
            'user_id' => User::factory()->create()->id,
            'provider' => 'paypal', 'provider_order_id' => 'PP-RECOVERY-1-deluxe',
            'product_key' => 'deluxe', 'amount_cents' => 2400, 'currency' => 'USD', 'status' => 'pending',
            'metadata' => ['checkout' => ['paypal_order_id' => 'PP-RECOVERY', 'capture_started' => true], 'product' => ['title' => 'Digital Album', 'kind' => 'digital', 'unlock_type' => 'album'], 'customer' => ['email' => 'buyer@example.test']],
            ...$attributes,
        ]);
    }
}
