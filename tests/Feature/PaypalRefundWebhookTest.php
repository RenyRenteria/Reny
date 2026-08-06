<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PaypalRefundWebhookTest extends TestCase
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

    public function test_refund_matches_by_capture_id_and_leaves_collision_untouched(): void
    {
        $target = $this->royalUser();
        $targetOrder = $this->paypalOrder($target, 'PAYPAL-100-1-merch', 'CAPTURE-100');

        // Different PayPal order whose ids deliberately collide on prefix/capture shape.
        $other = $this->royalUser();
        $otherOrder = $this->paypalOrder($other, 'PAYPAL-100B-1-merch', 'CAPTURE-999');

        $response = $this->postRefund([
            'resource' => [
                'supplementary_data' => [
                    'related_ids' => ['capture_id' => 'CAPTURE-100'],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'refunded')
            ->assertJsonPath('refunded_orders', 1)
            ->assertJsonPath('royal_status', 'refunded');

        $this->assertSame('refunded', $targetOrder->fresh()->status);
        $this->assertSame('refunded', $target->fresh()->royal_status);

        $this->assertSame('completed', $otherOrder->fresh()->status);
        $this->assertSame('royal_active', $other->fresh()->royal_status);
    }

    public function test_refund_by_order_id_does_not_match_similar_prefix(): void
    {
        $target = $this->royalUser();
        $targetOrder = $this->paypalOrder($target, 'PAYPAL-10-1-merch', 'CAPTURE-10');

        $neighbor = $this->royalUser();
        $neighborOrder = $this->paypalOrder($neighbor, 'PAYPAL-100-1-merch', 'CAPTURE-100');

        $response = $this->postRefund([
            'resource' => ['custom_id' => 'PAYPAL-10'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);

        $this->assertSame('refunded', $targetOrder->fresh()->status);
        $this->assertSame('completed', $neighborOrder->fresh()->status);
        $this->assertSame('royal_active', $neighbor->fresh()->royal_status);
    }

    public function test_refund_is_scoped_to_paypal_and_skips_local_orders(): void
    {
        $user = $this->royalUser();
        $paypalOrder = $this->paypalOrder($user, 'ORDER-X-1-merch', 'CAPTURE-X');
        $localOrder = Order::create([
            'user_id' => $user->id,
            'provider' => 'local',
            'provider_order_id' => 'ORDER-X-2-merch',
            'provider_capture_id' => null,
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => now()->addMonth(),
        ]);

        $response = $this->postRefund([
            'resource' => ['custom_id' => 'ORDER-X'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);

        $this->assertSame('refunded', $paypalOrder->fresh()->status);
        $this->assertSame('completed', $localOrder->fresh()->status);
    }

    public function test_refund_extracts_capture_id_from_links(): void
    {
        $user = $this->royalUser();
        $order = $this->paypalOrder($user, 'PAYPAL-LINK-1-merch', 'CAPTURE-LINK');

        $response = $this->postRefund([
            'resource' => [
                'id' => 'REFUND-XYZ',
                'links' => [
                    ['rel' => 'self', 'href' => 'https://api.paypal.com/v2/payments/refunds/REFUND-XYZ'],
                    ['rel' => 'up', 'href' => 'https://api.paypal.com/v2/payments/captures/CAPTURE-LINK'],
                ],
            ],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);

        $this->assertSame('refunded', $order->fresh()->status);
    }

    public function test_refund_ledger_is_idempotent_and_caps_multiple_partial_refunds(): void
    {
        $user = $this->royalUser();
        $order = $this->paypalOrder($user, 'PAYPAL-PARTIAL-1-merch', 'CAPTURE-PARTIAL');
        $payload = fn (string $refundId, string $amount): array => [
            'resource' => [
                'id' => $refundId,
                'amount' => ['value' => $amount, 'currency_code' => 'USD'],
                'supplementary_data' => [
                    'related_ids' => ['capture_id' => 'CAPTURE-PARTIAL'],
                ],
            ],
        ];

        $this->postRefund($payload('REFUND-PARTIAL-1', '12.00'))
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);
        $firstRefundedAt = $order->fresh()->refunded_at;

        $this->postRefund($payload('REFUND-PARTIAL-1', '12.00'))
            ->assertOk()
            ->assertJsonPath('refunded_orders', 0);
        $this->postRefund($payload('REFUND-PARTIAL-2', '12.00'))
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);
        $this->postRefund($payload('REFUND-PARTIAL-3', '50.00'))
            ->assertOk()
            ->assertJsonPath('refunded_orders', 1);
        $this->postRefund($payload('REFUND-PARTIAL-4', '10.00'))
            ->assertOk()
            ->assertJsonPath('refunded_orders', 0);

        $this->assertDatabaseCount('order_refunds', 3);
        $this->assertSame(4800, (int) $order->refunds()->sum('amount_cents'));
        $this->assertSame(4800, $order->fresh()->refund_amount_cents);
        $this->assertTrue($firstRefundedAt->equalTo($order->fresh()->refunded_at));
        $this->assertDatabaseHas('order_refunds', [
            'order_id' => $order->id,
            'provider_refund_id' => 'REFUND-PARTIAL-3',
            'amount_cents' => 2400,
        ]);
    }

    public function test_refund_without_reference_returns_422(): void
    {
        $this->postRefund(['resource' => []])->assertStatus(422);
    }

    public function test_refund_with_unknown_reference_returns_404(): void
    {
        $this->postRefund([
            'resource' => ['custom_id' => 'PAYPAL-DOES-NOT-EXIST'],
        ])->assertStatus(404);
    }

    private function royalUser(): User
    {
        return User::factory()->create([
            'royal_status' => 'royal_active',
            'royal_ends_at' => now()->addMonth(),
        ]);
    }

    private function paypalOrder(User $user, string $providerOrderId, string $captureId): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => $providerOrderId,
            'provider_capture_id' => $captureId,
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => now()->addMonth(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $resource
     */
    private function postRefund(array $resource): TestResponse
    {
        return $this->postJson('/paypal/refund', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            ...$resource,
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
            'PAYPAL-TRANSMISSION-TIME' => '2026-06-24T00:00:00Z',
            'PAYPAL-CERT-URL' => 'https://api.paypal.com/cert',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
        ];
    }
}
