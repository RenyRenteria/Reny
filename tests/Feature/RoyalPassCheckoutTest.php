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
            'provider_order_id' => 'PAYPAL-ORDER-100-1-merch',
            'product_key' => 'merch',
            'grants_royal_month' => true,
        ]);

        $this->assertDatabaseHas('billing_profiles', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'status' => 'active',
            'payment_method_summary' => 'PayPal',
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'purchase',
            'resource_key' => 'PAYPAL-ORDER-100-1-merch',
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_started',
            'resource_key' => 'PAYPAL-ORDER-100-1-merch',
        ]);
    }

    public function test_checkout_creates_paypal_order_before_capture(): void
    {
        $this->fakeCreatedOrder('PAYPAL-CREATED-100');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['deluxe', 'singles'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', 'PAYPAL-CREATED-100');
    }

    public function test_checkout_requires_valid_email_or_phone_identifier(): void
    {
        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'abc',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseMissing('users', [
            'email' => 'phone-@renyrenteria.local',
        ]);
    }

    public function test_checkout_rejects_non_usd_currency_before_payment_or_order_creation(): void
    {
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'DOP',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');

        $this->postJson('/checkout/local', [
            'identifier' => 'local@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'EUR',
            'local_reference' => 'ACH-20260619-4321',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('currency');

        Http::assertNothingSent();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_local_checkout_requires_valid_reference_and_creates_pending_order(): void
    {
        $this->postJson('/checkout/local', [
            'identifier' => 'local@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'local_reference' => 'abc',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('local_reference');

        $response = $this->postJson('/checkout/local', [
            'identifier' => 'local@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'local_reference' => 'ach 20260619 1234',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'pending')
            ->assertJsonPath('order_ids.0', 'LOCAL-ACH-20260619-1234-1-deluxe');

        $user = User::where('email', 'local@renyrenteria.com')->firstOrFail();

        $this->assertSame('open', $user->fresh()->royal_status);
        $this->assertNull($user->fresh()->royal_ends_at);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'local',
            'provider_order_id' => 'LOCAL-ACH-20260619-1234-1-deluxe',
            'product_key' => 'deluxe',
            'status' => 'pending',
            'grants_royal_month' => false,
        ]);

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'purchase_pending',
            'resource_key' => 'LOCAL-ACH-20260619-1234-1-deluxe',
        ]);
    }

    public function test_local_checkout_rejects_reused_reference(): void
    {
        $this->postJson('/checkout/local', [
            'identifier' => 'local-one@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'local_reference' => 'ACH-20260619-4321',
        ])->assertOk();

        $this->postJson('/checkout/local', [
            'identifier' => 'local-two@renyrenteria.com',
            'product_keys' => ['singles'],
            'currency' => 'USD',
            'local_reference' => 'ACH-20260619-4321',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('local_reference');
    }

    public function test_royal_pass_checkout_uses_four_ninety_nine_pricing(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-ROYAL-499', '4.99');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'royal-price@renyrenteria.com',
            'product_keys' => ['royal'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-ROYAL-499',
        ]);

        $response->assertOk();

        $user = User::where('email', 'royal-price@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider_order_id' => 'PAYPAL-ROYAL-499-1-royal',
            'product_key' => 'royal',
            'amount_cents' => 499,
            'currency' => 'USD',
            'status' => 'completed',
        ]);
    }

    public function test_digital_checkout_creates_library_unlock_visible_in_account(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-UNLOCK-300', '24.00');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'digital@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-UNLOCK-300',
        ]);

        $response->assertOk();

        $user = User::where('email', 'digital@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('user_unlocks', [
            'user_id' => $user->id,
            'product_key' => 'deluxe',
            'unlock_type' => 'album',
            'title' => 'Deluxe Digital Album',
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Deluxe Digital Album');
    }

    public function test_event_checkout_issues_internal_ticket_for_account_events(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-EVENT-400', '42.00');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'event@renyrenteria.com',
            'product_keys' => ['concert'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-EVENT-400',
        ]);

        $response->assertOk();

        $user = User::where('email', 'event@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('events', [
            'title' => 'Reny Live - Studio Night',
            'status' => 'scheduled',
        ]);
        $this->assertDatabaseHas('tickets', [
            'user_id' => $user->id,
            'holder_name' => $user->name,
            'status' => 'confirmed',
            'rsvp_status' => 'confirmed',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Reny Live - Studio Night');
    }

    public function test_duplicate_products_in_one_paypal_order_get_unique_provider_order_ids(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-DUPLICATE-500', '96.00');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'duplicate@renyrenteria.com',
            'product_keys' => ['merch', 'merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-DUPLICATE-500',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('order_ids.0', 'PAYPAL-DUPLICATE-500-1-merch')
            ->assertJsonPath('order_ids.1', 'PAYPAL-DUPLICATE-500-2-merch');

        $user = User::where('email', 'duplicate@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider_order_id' => 'PAYPAL-DUPLICATE-500-1-merch',
        ]);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider_order_id' => 'PAYPAL-DUPLICATE-500-2-merch',
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
            'provider_order_id' => 'PAYPAL-REFUND-200-1-merch',
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
            ->assertJsonPath('royal_status', 'refunded');

        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('refunded', $user->fresh()->royal_status);
        $this->assertFalse($user->fresh()->hasRoyalAccess());

        $this->assertDatabaseHas('access_events', [
            'user_id' => $user->id,
            'event_name' => 'membership_expired',
            'resource_key' => 'PAYPAL-REFUND-200-1-merch',
        ]);
    }

    public function test_refund_revokes_hub_unlocks_for_refunded_order(): void
    {
        $this->fakeSuccessfulCapture('PAYPAL-REFUND-UNLOCK', '24.00');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'refund-unlock@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-REFUND-UNLOCK',
        ])->assertOk();

        $user = User::where('email', 'refund-unlock@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('user_unlocks', [
            'user_id' => $user->id,
            'product_key' => 'deluxe',
            'status' => 'available',
        ]);

        $this->fakeSuccessfulWebhookVerification();

        $this->postJson('/paypal/refund', [
            'event_type' => 'PAYMENT.CAPTURE.REFUNDED',
            'resource' => [
                'supplementary_data' => [
                    'related_ids' => [
                        'order_id' => 'PAYPAL-REFUND-UNLOCK',
                    ],
                ],
            ],
        ], $this->paypalWebhookHeaders())->assertOk();

        $this->assertDatabaseHas('user_unlocks', [
            'user_id' => $user->id,
            'product_key' => 'deluxe',
            'status' => 'revoked',
        ]);
        $this->assertDatabaseHas('billing_profiles', [
            'user_id' => $user->id,
            'status' => 'refunded',
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
            ->assertSee('Load PayPal checkout')
            ->assertSee('Submit a bank/Yappy receipt')
            ->assertSee('Card checkout is not configured yet')
            ->assertSee('Apple Pay is not configured yet')
            ->assertSee(route('checkout.paypal.orders'))
            ->assertSee(route('checkout.paypal'))
            ->assertSee(route('checkout.local'));
    }

    private function fakeCreatedOrder(string $orderId): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v2/checkout/orders' => Http::response([
                'id' => $orderId,
                'status' => 'CREATED',
            ], 201),
        ]);
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
                'payer' => [
                    'payer_id' => 'PAYER-100',
                ],
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
