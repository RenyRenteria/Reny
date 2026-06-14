<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RoyalPassCheckoutTest extends TestCase
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
    }

    public function test_guest_product_checkout_creates_account_and_activates_royal_pass(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-ORDER-100', '48.00');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => '+1 (555) 303-4040',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-ORDER-100',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('royal_status', 'royal_active');

        $user = User::where('phone', '15553034040')->firstOrFail();

        $this->assertTrue($user->fresh()->hasRoyalAccess());
        $this->assertNotNull($user->royal_ends_at);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-ORDER-100-merch',
            'product_key' => 'merch',
            'grants_royal_month' => true,
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'purchase',
            'resource_key' => 'PAYPAL-ORDER-100-merch',
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_started',
            'resource_key' => 'PAYPAL-ORDER-100-merch',
        ]);
    }

    public function test_checkout_requires_paypal_order_capture(): void
    {
        $this->postJson('/checkout/paypal', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('users', [
            'email' => 'fan@renyrenteria.com',
            'royal_status' => 'royal_active',
        ]);
    }

    public function test_checkout_rejects_incomplete_paypal_capture(): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v2/checkout/orders/PAYPAL-ORDER-DECLINED/capture' => Http::response([
                'id' => 'PAYPAL-ORDER-DECLINED',
                'status' => 'PAYER_ACTION_REQUIRED',
            ], 200),
        ]);

        $this->postJson('/checkout/paypal', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-ORDER-DECLINED',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('users', [
            'email' => 'fan@renyrenteria.com',
            'royal_status' => 'royal_active',
        ]);
    }

    public function test_refund_revokes_royal_access_and_logs_expiration(): void
    {
        $user = User::factory()->royal()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-REFUND-200-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);

        $this->fakeSuccessfulWebhookVerification();

        $this->postJson('/paypal/refund', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL-REFUND-200',
                    ],
                ],
            ],
        ], $this->paypalWebhookHeaders())
            ->assertOk()
            ->assertJsonPath('status', 'refunded')
            ->assertJsonPath('refunded_orders', 1)
            ->assertJsonPath('royal_status', 'royal_expired');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_expired',
            'resource_key' => 'PAYPAL-REFUND-200-merch',
        ]);
    }

    public function test_unsigned_refund_webhook_cannot_revoke_royal_access(): void
    {
        $user = User::factory()->royal()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-REFUND-UNSIGNED-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);

        $this->postJson('/paypal/refund', [
            'provider_order_id' => $order->provider_order_id,
        ])->assertUnauthorized();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($user->fresh()->hasRoyalAccess());
    }

    public function test_signed_non_refund_webhook_cannot_revoke_royal_access(): void
    {
        $user = User::factory()->royal()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-NON-REFUND-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'completed',
            'grants_royal_month' => true,
            'royal_granted_until' => $user->royal_ends_at,
        ]);

        $this->fakeSuccessfulWebhookVerification();

        $this->postJson('/paypal/refund', [
            'event_type' => 'PAYMENT.CAPTURE.COMPLETED',
            'resource' => [
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL-NON-REFUND',
                    ],
                ],
            ],
        ], $this->paypalWebhookHeaders())->assertUnprocessable();

        $this->assertSame('completed', $order->fresh()->status);
        $this->assertTrue($user->fresh()->hasRoyalAccess());
    }

    public function test_store_checkout_discloses_royal_month_grant_and_paypal_endpoint(): void
    {
        $this->get('/store')
            ->assertOk()
            ->assertSee('Every completed purchase activates Royal Pass for 1 month')
            ->assertSee('Complete with PayPal')
            ->assertSee(route('checkout.paypal'));
    }

    private function fakeSuccessfulCapture(string $orderId, string $amount): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            "https://paypal.test/v2/checkout/orders/{$orderId}/capture" => Http::response([
                'id' => $orderId,
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'id' => 'CAPTURE-100',
                                    'status' => 'COMPLETED',
                                    'amount' => [
                                        'currency_code' => 'USD',
                                        'value' => $amount,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 201),
        ]);
    }

    private function fakeSuccessfulWebhookVerification(): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v1/notifications/verify-webhook-signature' => Http::response([
                'verification_status' => 'SUCCESS',
            ], 200),
        ]);
    }

    /**
     * @return array<string, string>
     */
    private function paypalWebhookHeaders(): array
    {
        return [
            'PAYPAL-TRANSMISSION-ID' => 'transmission-id',
            'PAYPAL-TRANSMISSION-TIME' => '2026-06-14T22:45:00Z',
            'PAYPAL-CERT-URL' => 'https://paypal.test/cert',
            'PAYPAL-AUTH-ALGO' => 'SHA256withRSA',
            'PAYPAL-TRANSMISSION-SIG' => 'signature',
        ];
    }
}
