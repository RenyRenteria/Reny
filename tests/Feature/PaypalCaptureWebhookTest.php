<?php

namespace Tests\Feature;

use App\Models\AccessEvent;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\PayPalSandboxE2eControl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class PaypalCaptureWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.paypal.base_url' => 'https://paypal.test',
            'services.paypal.client_id' => 'client-id',
            'services.paypal.client_secret' => 'client-secret',
            'services.paypal.webhook_id' => 'webhook-id',
        ]);

        $this->fakeVerifiedWebhook();
    }

    public function test_signed_capture_webhook_recovers_payment_review_exactly_once(): void
    {
        $user = User::factory()->create([
            'royal_status' => 'open',
            'royal_ends_at' => null,
        ]);
        $order = $this->paymentReviewOrder($user);
        Log::spy();

        $this->postCapture()
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('completed_orders', 1);

        $firstRoyalEndsAt = $user->fresh()->royal_ends_at;

        $this->postCapture()
            ->assertOk()
            ->assertJsonPath('status', 'already_completed')
            ->assertJsonPath('completed_orders', 0);

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('CAPTURE-REVIEW-100', $order->fresh()->provider_capture_id);
        $this->assertTrue($user->fresh()->hasRoyalAccess());
        $this->assertTrue($firstRoyalEndsAt->equalTo($user->fresh()->royal_ends_at));
        $this->assertDatabaseHas('billing_profiles', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'status' => 'active',
        ]);
        $this->assertSame(1, AccessEvent::query()->where('event_name', 'purchase')->count());
        $this->assertSame(1, AccessEvent::query()->where('event_name', 'membership_started')->count());

        Log::shouldHaveReceived('info')
            ->with('PayPal capture webhook reconciled checkout.', Mockery::on(function (array $context): bool {
                $serialized = json_encode($context);

                return $context['paypal_stage'] === 'capture_webhook'
                    && $context['paypal_endpoint'] === '/paypal/webhook'
                    && $context['paypal_http_status'] === 200
                    && $context['paypal_order_reference'] === substr(hash('sha256', 'PAYPAL-REVIEW-100'), 0, 16)
                    && $context['paypal_capture_reference'] === substr(hash('sha256', 'CAPTURE-REVIEW-100'), 0, 16)
                    && ! str_contains($serialized, 'PAYPAL-REVIEW-100')
                    && ! str_contains($serialized, 'CAPTURE-REVIEW-100');
            }))
            ->twice();
    }

    public function test_unsigned_capture_webhook_cannot_finalize_payment_review(): void
    {
        $user = User::factory()->create(['royal_status' => 'open']);
        $order = $this->paymentReviewOrder($user);
        config(['services.paypal.webhook_id' => null]);

        $this->postCapture()->assertUnauthorized();

        $this->assertSame('payment_review', $order->fresh()->status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());
        $this->assertDatabaseCount('billing_profiles', 0);
    }

    public function test_capture_webhook_with_wrong_amount_stays_in_payment_review(): void
    {
        $user = User::factory()->create(['royal_status' => 'open']);
        $order = $this->paymentReviewOrder($user);

        $this->postCapture(amount: '47.99')
            ->assertOk()
            ->assertJsonPath('status', 'payment_review')
            ->assertJsonPath('reason', 'capture_amount_or_currency_mismatch');

        $this->assertSame('payment_review', $order->fresh()->status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());
        $this->assertDatabaseCount('billing_profiles', 0);
        $this->assertDatabaseCount('access_events', 0);
    }

    public function test_sandbox_fault_gate_holds_then_releases_signed_capture_webhook(): void
    {
        config([
            'services.paypal.base_url' => 'https://api-m.sandbox.paypal.com',
            'services.paypal.e2e.enabled' => true,
            'services.paypal.e2e.control_token' => 'sandbox-control-token',
            'services.paypal.e2e.existing_customer_email' => 'qa+paypal-existing@renyrenteria.test',
        ]);
        Http::fake([
            'https://api-m.sandbox.paypal.com/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token'], 200),
            'https://api-m.sandbox.paypal.com/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);
        $user = User::factory()->create(['royal_status' => 'open']);
        $order = $this->paymentReviewOrder($user);
        $control = app(PayPalSandboxE2eControl::class);
        $control->armPostCaptureFailure();

        $this->postCapture()
            ->assertStatus(503)
            ->assertJsonPath('status', 'retry');
        $this->assertSame('payment_review', $order->fresh()->status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());

        $control->releaseCaptureWebhook();

        $this->postCapture()
            ->assertOk()
            ->assertJsonPath('status', 'completed');
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($user->fresh()->hasRoyalAccess());
    }

    public function test_valid_unrelated_or_unknown_capture_webhooks_are_acknowledged(): void
    {
        $this->postJson('/paypal/webhook', [
            'id' => 'WH-IGNORED-1',
            'event_type' => 'CHECKOUT.ORDER.APPROVED',
            'resource' => [],
        ], $this->paypalHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'ignored')
            ->assertJsonPath('reason', 'event_not_subscribed');

        $this->postCapture(orderId: 'PAYPAL-UNKNOWN')
            ->assertOk()
            ->assertJsonPath('status', 'ignored')
            ->assertJsonPath('reason', 'order_not_found');
    }

    private function paymentReviewOrder(User $user): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-REVIEW-100-1-merch',
            'provider_capture_id' => 'CAPTURE-REVIEW-100',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'payment_review',
            'grants_royal_month' => true,
        ]);
    }

    private function postCapture(
        string $orderId = 'PAYPAL-REVIEW-100',
        string $captureId = 'CAPTURE-REVIEW-100',
        string $amount = '48.00',
    ): TestResponse {
        return $this->postJson('/paypal/webhook', [
            'id' => 'WH-CAPTURE-COMPLETED-100',
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'id' => $captureId,
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => 'USD',
                    'value' => $amount,
                ],
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => $orderId,
                    ],
                ],
            ],
        ], $this->paypalHeaders());
    }

    private function fakeVerifiedWebhook(): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response(['access_token' => 'paypal-token'], 200),
            'https://paypal.test/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function paypalHeaders(): array
    {
        return [
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
            'PAYPAL-TRANSMISSION-TIME' => '2026-08-16T21:30:00Z',
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
        ];
    }
}
