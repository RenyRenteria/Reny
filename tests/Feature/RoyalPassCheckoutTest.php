<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\FanEvent;
use App\Models\Order;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserUnlock;
use App\Services\RoyalPassService;
use App\Services\UserHubPurchaseSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
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
        $this->createPendingPayPalOrder('+1 (555) 303-4040', ['merch'], 'PAYPAL-ORDER-100');
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
            'provider_capture_id' => 'CAPTURE-100',
            'product_key' => 'merch',
            'status' => 'completed',
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
            'customer_name' => 'Reny Fan',
            'customer_email' => 'fan@renyrenteria.com',
            'customer_phone' => '+50760000000',
            'customer_country' => 'Panama',
            'product_keys' => ['deluxe', 'singles'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', 'PAYPAL-CREATED-100')
            ->assertJsonPath('order_ids.0', 'PAYPAL-CREATED-100-1-deluxe')
            ->assertJsonPath('order_ids.1', 'PAYPAL-CREATED-100-2-singles');

        $user = User::where('email', 'fan@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-CREATED-100-1-deluxe',
            'product_key' => 'deluxe',
            'amount_cents' => 2400,
            'status' => 'pending',
        ]);

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-CREATED-100-2-singles',
            'product_key' => 'singles',
            'amount_cents' => 800,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_requires_customer_details_before_paypal_order_creation(): void
    {
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'customer_name',
                'customer_email',
                'customer_phone',
                'customer_country',
            ]);

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_public_paypal_order_creation_requires_login_for_existing_email(): void
    {
        User::factory()->create([
            'email' => 'existing@renyrenteria.com',
        ]);
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'existing@renyrenteria.com',
            'customer_name' => 'Existing Fan',
            'customer_email' => 'existing@renyrenteria.com',
            'customer_phone' => '+50760000006',
            'customer_country' => 'Panama',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_public_paypal_order_creation_requires_login_for_existing_phone(): void
    {
        User::factory()->create([
            'phone' => '50760000009',
        ]);
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => '+507 6000-0009',
            'customer_name' => 'Existing Phone Fan',
            'customer_email' => 'existing-phone@renyrenteria.com',
            'customer_phone' => '+50760000009',
            'customer_country' => 'Panama',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_public_paypal_order_creation_does_not_authenticate_guest_customer(): void
    {
        $this->fakeCreatedOrder('PAYPAL-GUEST-NO-AUTH');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'guest-no-auth@renyrenteria.com',
            'customer_name' => 'Guest Fan',
            'customer_email' => 'guest-no-auth@renyrenteria.com',
            'customer_phone' => '+50760000007',
            'customer_country' => 'Panama',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', 'PAYPAL-GUEST-NO-AUTH');

        $this->assertGuest();
        $this->assertDatabaseHas('users', [
            'email' => 'guest-no-auth@renyrenteria.com',
        ]);
    }

    public function test_authenticated_checkout_attaches_purchase_to_logged_in_user(): void
    {
        $user = User::factory()->create([
            'email' => 'buyer@renyrenteria.com',
            'phone' => '50761111111',
        ]);
        $otherUser = User::factory()->create([
            'email' => 'other@renyrenteria.com',
            'phone' => '50762222222',
        ]);

        $this->actingAs($user);
        $this->fakeCreatedOrder('PAYPAL-AUTH-OWNER');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => $otherUser->email,
            'customer_name' => 'Authenticated Buyer',
            'customer_email' => $otherUser->email,
            'customer_phone' => '+50762222222',
            'customer_country' => 'Panama',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('paypal_order_id', 'PAYPAL-AUTH-OWNER');

        $this->fakeSuccessfulCapture('PAYPAL-AUTH-OWNER', '24.00', 'CAPTURE-AUTH-OWNER');

        $this->postJson('/checkout/paypal', [
            'identifier' => $otherUser->email,
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-AUTH-OWNER',
        ])->assertOk();

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider_order_id' => 'PAYPAL-AUTH-OWNER-1-deluxe',
            'provider_capture_id' => 'CAPTURE-AUTH-OWNER',
            'status' => 'completed',
        ]);
        $this->assertDatabaseMissing('orders', [
            'user_id' => $otherUser->id,
            'provider_order_id' => 'PAYPAL-AUTH-OWNER-1-deluxe',
        ]);
    }

    public function test_checkout_stores_customer_capture_details_on_pending_paypal_order(): void
    {
        $this->fakeCreatedOrder('PAYPAL-CUSTOMER-100');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'customer@renyrenteria.com',
            'customer_name' => 'Reny Fan',
            'customer_email' => 'customer@renyrenteria.com',
            'customer_phone' => '+50760000000',
            'customer_country' => 'Panama',
            'product_keys' => ['listening'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', 'PAYPAL-CUSTOMER-100');

        $order = Order::query()
            ->where('provider_order_id', 'PAYPAL-CUSTOMER-100-1-listening')
            ->firstOrFail();

        $this->assertSame('Reny Fan', data_get($order->metadata, 'customer.name'));
        $this->assertSame('customer@renyrenteria.com', data_get($order->metadata, 'customer.email'));
        $this->assertSame('+50760000000', data_get($order->metadata, 'customer.phone'));
        $this->assertSame('Panama', data_get($order->metadata, 'customer.country'));
    }

    public function test_guest_paypal_capture_rejects_existing_account_identifier_without_session_ownership(): void
    {
        $victim = User::factory()->create([
            'email' => 'victim@renyrenteria.com',
            'phone' => '50760000008',
        ]);

        Order::create([
            'user_id' => $victim->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-VICTIM-100-1-deluxe',
            'product_key' => 'deluxe',
            'amount_cents' => 2400,
            'currency' => 'USD',
            'status' => 'pending',
            'grants_royal_month' => true,
        ]);
        Http::fake();

        $this->postJson('/checkout/paypal', [
            'identifier' => 'victim@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-VICTIM-100',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        $this->assertGuest();
        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-VICTIM-100-1-deluxe',
            'provider_capture_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_creates_paypal_order_with_all_ticket_line_items(): void
    {
        $this->fakeCreatedOrder('PAYPAL-TICKETS-ITEMS');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'tickets-items@renyrenteria.com',
            'customer_name' => 'Tickets Fan',
            'customer_email' => 'tickets-items@renyrenteria.com',
            'customer_phone' => '+50760000001',
            'customer_country' => 'Panama',
            'product_keys' => ['concert', 'concert', 'listening'],
            'currency' => 'USD',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', 'PAYPAL-TICKETS-ITEMS')
            ->assertJsonPath('order_ids.0', 'PAYPAL-TICKETS-ITEMS-1-concert')
            ->assertJsonPath('order_ids.1', 'PAYPAL-TICKETS-ITEMS-2-concert')
            ->assertJsonPath('order_ids.2', 'PAYPAL-TICKETS-ITEMS-3-listening');

        Http::assertSent(function ($request): bool {
            if ($request->url() !== 'https://paypal.test/v2/checkout/orders') {
                return false;
            }

            $payload = $request->data();
            $items = collect(data_get($payload, 'purchase_units.0.items', []));

            return data_get($payload, 'purchase_units.0.amount.value') === '99.00'
                && data_get($payload, 'purchase_units.0.amount.breakdown.item_total.value') === '99.00'
                && $items->contains(fn (array $item): bool => $item['name'] === 'Reny Live - Studio Night'
                    && $item['quantity'] === '2'
                    && data_get($item, 'unit_amount.value') === '42.00')
                && $items->contains(fn (array $item): bool => $item['name'] === 'Festival de la Rosa Dorada'
                    && $item['quantity'] === '1'
                    && data_get($item, 'unit_amount.value') === '15.00');
        });
    }

    public function test_checkout_rejects_empty_cart_before_paypal_order_creation(): void
    {
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@renyrenteria.com',
            'customer_name' => 'Reny Fan',
            'customer_email' => 'fan@renyrenteria.com',
            'customer_phone' => '+50760000002',
            'customer_country' => 'Panama',
            'product_keys' => [],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('product_keys');

        Http::assertNothingSent();
    }

    public function test_checkout_rejects_invalid_contact_identifier_before_capture(): void
    {
        Http::fake();

        $this->postJson('/checkout/paypal', [
            'identifier' => 'abc',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-INVALID-CONTACT',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_checkout_rejects_non_usd_currency_before_payment_or_order_creation(): void
    {
        Http::fake();

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'fan@renyrenteria.com',
            'customer_name' => 'Reny Fan',
            'customer_email' => 'fan@renyrenteria.com',
            'customer_phone' => '+50760000003',
            'customer_country' => 'Panama',
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

    public function test_public_local_checkout_requires_login_for_existing_identifier(): void
    {
        User::factory()->create([
            'email' => 'local-existing@renyrenteria.com',
        ]);

        $this->postJson('/checkout/local', [
            'identifier' => 'local-existing@renyrenteria.com',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'local_reference' => 'ACH-20260620-4321',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_public_local_checkout_requires_login_for_existing_phone(): void
    {
        User::factory()->create([
            'phone' => '50760000010',
        ]);

        $this->postJson('/checkout/local', [
            'identifier' => '+507 6000-0010',
            'product_keys' => ['deluxe'],
            'currency' => 'USD',
            'local_reference' => 'ACH-20260620-9876',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('identifier');

        $this->assertGuest();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_royal_pass_service_does_not_return_existing_customer_by_default(): void
    {
        $emailUser = User::factory()->create([
            'email' => 'service-existing@renyrenteria.com',
        ]);
        $phoneUser = User::factory()->create([
            'phone' => '50760000011',
        ]);
        $service = app(RoyalPassService::class);

        try {
            $service->findOrCreateCustomer($emailUser->email);
            $this->fail('Existing email customer should require verified ownership.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identifier', $exception->errors());
        }

        try {
            $service->findOrCreateCustomer('+507 6000-0011');
            $this->fail('Existing phone customer should require verified ownership.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('identifier', $exception->errors());
        }

        $this->assertTrue($emailUser->is($service->findOrCreateCustomer($emailUser->email, allowExisting: true)));
        $this->assertTrue($phoneUser->is($service->findOrCreateCustomer('+507 6000-0011', allowExisting: true)));
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
        $this->createPendingPayPalOrder('royal-price@renyrenteria.com', ['royal'], 'PAYPAL-ROYAL-499');
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
        $this->createPendingPayPalOrder('digital@renyrenteria.com', ['deluxe'], 'PAYPAL-UNLOCK-300');
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

    public function test_cms_digital_checkout_creates_editorial_content_entitlement(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'Backstage Stems Pack',
            'slug' => 'backstage-stems-pack',
            'purchase_key' => 'cms-digital-stems',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 1200,
            ],
        ]);

        $this->createPendingPayPalOrder('cms-digital@renyrenteria.com', ['cms-digital-stems'], 'PAYPAL-CMS-DIGITAL');
        $this->fakeSuccessfulCapture('PAYPAL-CMS-DIGITAL', '12.00', 'CAPTURE-CMS-DIGITAL');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'cms-digital@renyrenteria.com',
            'product_keys' => ['cms-digital-stems'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-CMS-DIGITAL',
        ])->assertOk();

        $user = User::where('email', 'cms-digital@renyrenteria.com')->firstOrFail();
        $order = Order::where('provider_order_id', 'PAYPAL-CMS-DIGITAL-1-cms-digital-stems')->firstOrFail();

        $this->assertSame(1200, $order->amount_cents);
        $this->assertSame('Backstage Stems Pack', data_get($order->metadata, 'product.title'));
        $this->assertSame('editorial_content', data_get($order->metadata, 'product.source_type'));
        $this->assertSame((string) $content->id, data_get($order->metadata, 'product.source_id'));

        $this->assertDatabaseHas('user_unlocks', [
            'user_id' => $user->id,
            'order_id' => $order->id,
            'product_key' => 'cms-digital-stems',
            'unlock_type' => 'digital',
            'title' => 'Backstage Stems Pack',
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Backstage Stems Pack');
    }

    public function test_cms_art_drop_checkout_creates_drop_entitlement(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Drop->value,
            'title' => 'Capri Contact Sheet',
            'slug' => 'capri-contact-sheet',
            'purchase_key' => 'capri-art-drop',
            'metadata' => [
                'drop_kind' => 'content',
                'price_cents' => 8600,
            ],
        ]);

        $this->createPendingPayPalOrder('artdrop@renyrenteria.com', ['capri-art-drop'], 'PAYPAL-CMS-DROP');
        $this->fakeSuccessfulCapture('PAYPAL-CMS-DROP', '86.00', 'CAPTURE-CMS-DROP');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'artdrop@renyrenteria.com',
            'product_keys' => ['capri-art-drop'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-CMS-DROP',
        ])->assertOk();

        $user = User::where('email', 'artdrop@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('user_unlocks', [
            'user_id' => $user->id,
            'product_key' => 'capri-art-drop',
            'unlock_type' => 'drop',
            'title' => 'Capri Contact Sheet',
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
        ]);

        $this->actingAs($user)
            ->get('/account')
            ->assertOk()
            ->assertSee('Capri Contact Sheet');
    }

    public function test_failed_cms_digital_capture_does_not_grant_entitlement(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'Failed Digital Pack',
            'slug' => 'failed-digital-pack',
            'purchase_key' => 'failed-digital-pack',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 1200,
            ],
        ]);

        $this->createPendingPayPalOrder('failed-digital@renyrenteria.com', ['failed-digital-pack'], 'PAYPAL-CMS-FAILED');

        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v2/checkout/orders/PAYPAL-CMS-FAILED/capture' => Http::response([
                'id' => 'PAYPAL-CMS-FAILED',
                'status' => 'PAYER_ACTION_REQUIRED',
            ], 200),
        ]);

        $this->postJson('/checkout/paypal', [
            'identifier' => 'failed-digital@renyrenteria.com',
            'product_keys' => ['failed-digital-pack'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-CMS-FAILED',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        $this->assertDatabaseCount('user_unlocks', 0);
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-CMS-FAILED-1-failed-digital-pack',
            'provider_capture_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_entitlement_sync_is_idempotent_for_replayed_completion(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'Replay Digital Pack',
            'slug' => 'replay-digital-pack',
            'purchase_key' => 'replay-digital-pack',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 1200,
            ],
        ]);

        $this->createPendingPayPalOrder('replay-digital@renyrenteria.com', ['replay-digital-pack'], 'PAYPAL-CMS-REPLAY');
        $this->fakeSuccessfulCapture('PAYPAL-CMS-REPLAY', '12.00', 'CAPTURE-CMS-REPLAY');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'replay-digital@renyrenteria.com',
            'product_keys' => ['replay-digital-pack'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-CMS-REPLAY',
        ])->assertOk();

        $user = User::where('email', 'replay-digital@renyrenteria.com')->firstOrFail();
        $order = Order::where('provider_order_id', 'PAYPAL-CMS-REPLAY-1-replay-digital-pack')->firstOrFail();

        app(UserHubPurchaseSync::class)->recordCompletedOrder($user, $order, [
            'capture_id' => 'CAPTURE-CMS-REPLAY',
            'payer_id' => 'PAYER-100',
        ]);

        $this->assertSame(1, UserUnlock::query()
            ->where('user_id', $user->id)
            ->where('product_key', 'replay-digital-pack')
            ->where('status', 'available')
            ->count());
    }

    public function test_event_checkout_issues_internal_ticket_for_account_events(): void
    {
        $this->createPendingPayPalOrder('event@renyrenteria.com', ['concert'], 'PAYPAL-EVENT-400');
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

    public function test_multi_ticket_same_event_checkout_issues_one_ticket_per_item(): void
    {
        $this->createPendingPayPalOrder('same-event@renyrenteria.com', ['concert', 'concert'], 'PAYPAL-SAME-EVENT');
        $this->fakeSuccessfulCapture('PAYPAL-SAME-EVENT', '84.00', 'CAPTURE-SAME-EVENT');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'same-event@renyrenteria.com',
            'product_keys' => ['concert', 'concert'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-SAME-EVENT',
        ])
            ->assertOk()
            ->assertJsonPath('order_ids.0', 'PAYPAL-SAME-EVENT-1-concert')
            ->assertJsonPath('order_ids.1', 'PAYPAL-SAME-EVENT-2-concert');

        $user = User::where('email', 'same-event@renyrenteria.com')->firstOrFail();
        $event = FanEvent::where('title', 'Reny Live - Studio Night')->firstOrFail();

        $this->assertSame(2, Ticket::query()
            ->where('user_id', $user->id)
            ->where('event_id', $event->id)
            ->where('status', 'confirmed')
            ->count());

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-SAME-EVENT-1-concert',
            'provider_capture_id' => 'CAPTURE-SAME-EVENT',
            'status' => 'completed',
        ]);
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-SAME-EVENT-2-concert',
            'provider_capture_id' => 'CAPTURE-SAME-EVENT',
            'status' => 'completed',
        ]);
    }

    public function test_multi_event_checkout_issues_tickets_for_each_event(): void
    {
        $this->createPendingPayPalOrder('multi-event@renyrenteria.com', ['concert', 'listening'], 'PAYPAL-MULTI-EVENT');
        $this->fakeSuccessfulCapture('PAYPAL-MULTI-EVENT', '57.00', 'CAPTURE-MULTI-EVENT');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'multi-event@renyrenteria.com',
            'product_keys' => ['concert', 'listening'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-MULTI-EVENT',
        ])
            ->assertOk()
            ->assertJsonPath('order_ids.0', 'PAYPAL-MULTI-EVENT-1-concert')
            ->assertJsonPath('order_ids.1', 'PAYPAL-MULTI-EVENT-2-listening');

        $user = User::where('email', 'multi-event@renyrenteria.com')->firstOrFail();
        $concert = FanEvent::where('title', 'Reny Live - Studio Night')->firstOrFail();
        $listening = FanEvent::where('title', 'Festival de la Rosa Dorada')->firstOrFail();

        $this->assertSame(1, Ticket::query()
            ->where('user_id', $user->id)
            ->where('event_id', $concert->id)
            ->where('status', 'confirmed')
            ->count());
        $this->assertSame(1, Ticket::query()
            ->where('user_id', $user->id)
            ->where('event_id', $listening->id)
            ->where('status', 'confirmed')
            ->count());
    }

    public function test_ticket_checkout_rejects_paypal_capture_total_mismatch(): void
    {
        $this->createPendingPayPalOrder('ticket-mismatch@renyrenteria.com', ['concert', 'listening'], 'PAYPAL-TICKET-MISMATCH');
        $this->fakeSuccessfulCapture('PAYPAL-TICKET-MISMATCH', '42.00', 'CAPTURE-TICKET-MISMATCH');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'ticket-mismatch@renyrenteria.com',
            'product_keys' => ['concert', 'listening'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-TICKET-MISMATCH',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-TICKET-MISMATCH-1-concert',
            'provider_capture_id' => null,
            'status' => 'pending',
        ]);
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-TICKET-MISMATCH-2-listening',
            'provider_capture_id' => null,
            'status' => 'pending',
        ]);
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_ticket_checkout_ignores_client_items_not_in_pending_paypal_order(): void
    {
        $this->createPendingPayPalOrder('ticket-snapshot@renyrenteria.com', ['concert'], 'PAYPAL-TICKET-SNAPSHOT');
        $this->fakeSuccessfulCapture('PAYPAL-TICKET-SNAPSHOT', '42.00', 'CAPTURE-TICKET-SNAPSHOT');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'ticket-snapshot@renyrenteria.com',
            'product_keys' => ['concert', 'listening'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-TICKET-SNAPSHOT',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('order_ids.0', 'PAYPAL-TICKET-SNAPSHOT-1-concert');

        $this->assertCount(1, $response->json('order_ids'));

        $user = User::where('email', 'ticket-snapshot@renyrenteria.com')->firstOrFail();
        $concert = FanEvent::where('title', 'Reny Live - Studio Night')->firstOrFail();

        $this->assertSame(1, Ticket::query()
            ->where('user_id', $user->id)
            ->where('event_id', $concert->id)
            ->where('status', 'confirmed')
            ->count());
        $this->assertDatabaseMissing('events', [
            'title' => 'Festival de la Rosa Dorada',
        ]);
    }

    public function test_duplicate_products_in_one_paypal_order_get_unique_provider_order_ids(): void
    {
        $this->createPendingPayPalOrder('duplicate@renyrenteria.com', ['merch', 'merch'], 'PAYPAL-DUPLICATE-500');
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

    public function test_checkout_uses_pending_order_snapshot_instead_of_client_payload(): void
    {
        $this->createPendingPayPalOrder('snapshot@renyrenteria.com', ['merch'], 'PAYPAL-SNAPSHOT-100');
        $this->fakeSuccessfulCapture('PAYPAL-SNAPSHOT-100', '48.00');

        $response = $this->postJson('/checkout/paypal', [
            'identifier' => 'snapshot@renyrenteria.com',
            'product_keys' => ['royal'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-SNAPSHOT-100',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('order_ids.0', 'PAYPAL-SNAPSHOT-100-1-merch');

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-SNAPSHOT-100-1-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'status' => 'completed',
        ]);

        $this->assertDatabaseMissing('orders', [
            'provider_order_id' => 'PAYPAL-SNAPSHOT-100-1-royal',
        ]);
    }

    public function test_checkout_rejects_paypal_order_owned_by_another_pending_checkout(): void
    {
        $this->createPendingPayPalOrder('owner@renyrenteria.com', ['merch'], 'PAYPAL-OWNER-100');
        Auth::logout();
        $this->flushSession();
        Http::fake();

        $this->postJson('/checkout/paypal', [
            'identifier' => 'other@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-OWNER-100',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-OWNER-100-1-merch',
            'status' => 'pending',
            'provider_capture_id' => null,
        ]);
    }

    public function test_authenticated_paypal_capture_requires_original_checkout_session(): void
    {
        $user = User::factory()->create([
            'email' => 'session-owner@renyrenteria.com',
        ]);

        $this->actingAs($user);
        $this->fakeCreatedOrder('PAYPAL-AUTH-SESSION');

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => $user->email,
            'customer_name' => 'Session Owner',
            'customer_email' => $user->email,
            'customer_phone' => '+50760000012',
            'customer_country' => 'Panama',
            'product_keys' => ['merch'],
            'currency' => 'USD',
        ])->assertOk();

        Auth::logout();
        $this->flushSession();
        $this->actingAs($user);
        Http::fake();

        $this->postJson('/checkout/paypal', [
            'identifier' => $user->email,
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-AUTH-SESSION',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        Http::assertNothingSent();
        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider_order_id' => 'PAYPAL-AUTH-SESSION-1-merch',
            'status' => 'pending',
            'provider_capture_id' => null,
        ]);
    }

    public function test_cancel_paypal_order_marks_pending_order_cancelled(): void
    {
        $this->createPendingPayPalOrder('cancel@renyrenteria.com', ['merch'], 'PAYPAL-CANCEL-100');

        $this->postJson('/checkout/paypal/orders/cancel', [
            'paypal_order_id' => 'PAYPAL-CANCEL-100',
        ])
            ->assertOk()
            ->assertJsonPath('status', 'cancelled')
            ->assertJsonPath('cancelled_orders', 1);

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-CANCEL-100-1-merch',
            'status' => 'cancelled',
            'provider_capture_id' => null,
        ]);
    }

    public function test_failed_paypal_order_creation_marks_pending_order_failed(): void
    {
        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v2/checkout/orders' => Http::response([
                'message' => 'PayPal unavailable',
            ], 500),
        ]);

        $this->postJson('/checkout/paypal/orders', [
            'identifier' => 'failed@renyrenteria.com',
            'customer_name' => 'Failed Fan',
            'customer_email' => 'failed@renyrenteria.com',
            'customer_phone' => '+50760000004',
            'customer_country' => 'Panama',
            'product_keys' => ['merch'],
            'currency' => 'USD',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal');

        $user = User::where('email', 'failed@renyrenteria.com')->firstOrFail();

        $this->assertDatabaseHas('orders', [
            'user_id' => $user->id,
            'provider' => 'paypal',
            'product_key' => 'merch',
            'status' => 'failed',
            'provider_capture_id' => null,
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
        $this->createPendingPayPalOrder('fan@renyrenteria.com', ['merch'], 'PAYPAL-ORDER-DECLINED');

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

        $this->assertDatabaseMissing('users', [
            'email' => 'fan@renyrenteria.com',
            'royal_status' => 'royal_active',
        ]);
        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-ORDER-DECLINED-1-merch',
            'status' => 'pending',
        ]);
    }

    public function test_checkout_rejects_completed_paypal_capture_without_capture_id(): void
    {
        $this->createPendingPayPalOrder('fan@renyrenteria.com', ['merch'], 'PAYPAL-NO-CAPTURE-ID');

        Http::fake([
            'https://paypal.test/v1/oauth2/token' => Http::response([
                'access_token' => 'paypal-token',
            ], 200),
            'https://paypal.test/v2/checkout/orders/PAYPAL-NO-CAPTURE-ID/capture' => Http::response([
                'id' => 'PAYPAL-NO-CAPTURE-ID',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'payments' => [
                            'captures' => [
                                [
                                    'status' => 'COMPLETED',
                                    'amount' => [
                                        'currency_code' => 'USD',
                                        'value' => '48.00',
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ], 201),
        ]);

        $this->postJson('/checkout/paypal', [
            'identifier' => 'fan@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-NO-CAPTURE-ID',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-NO-CAPTURE-ID-1-merch',
            'provider_capture_id' => null,
            'status' => 'pending',
        ]);
    }

    public function test_checkout_rejects_reused_paypal_capture(): void
    {
        $this->createPendingPayPalOrder('first@renyrenteria.com', ['merch'], 'PAYPAL-REPLAY-100');
        $this->fakeSuccessfulCapture('PAYPAL-REPLAY-100', '48.00');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'first@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-REPLAY-100',
        ])->assertOk();

        $user = User::where('email', 'first@renyrenteria.com')->firstOrFail();
        Order::create([
            'user_id' => $user->id,
            'provider' => 'paypal',
            'provider_order_id' => 'PAYPAL-REPLAY-200-1-merch',
            'product_key' => 'merch',
            'amount_cents' => 4800,
            'currency' => 'USD',
            'status' => 'pending',
            'grants_royal_month' => true,
        ]);

        $this->fakeSuccessfulCapture('PAYPAL-REPLAY-200', '48.00');

        $this->postJson('/checkout/paypal', [
            'identifier' => 'first@renyrenteria.com',
            'product_keys' => ['merch'],
            'currency' => 'USD',
            'paypal_order_id' => 'PAYPAL-REPLAY-200',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('paypal_order_id');

        $this->assertDatabaseHas('orders', [
            'provider_order_id' => 'PAYPAL-REPLAY-200-1-merch',
            'status' => 'pending',
            'provider_capture_id' => null,
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
        $this->createPendingPayPalOrder('refund-unlock@renyrenteria.com', ['deluxe'], 'PAYPAL-REFUND-UNLOCK');
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
            ->assertSee('PayPal checkout is charged in USD')
            ->assertSee('PayPal Checkout')
            ->assertSee('id="purchaseConfirmationLayer"', false)
            ->assertSee('Royal Pass confirmed')
            ->assertSee('id="paypalButtons"', false)
            ->assertSee(route('checkout.paypal.orders'))
            ->assertSee(route('checkout.paypal.orders.cancel'))
            ->assertSee(route('checkout.paypal'))
            ->assertDontSee('Load PayPal checkout')
            ->assertDontSee('Submit a bank/Yappy receipt')
            ->assertDontSee(route('checkout.local'));
    }

    public function test_store_exposes_cms_digital_and_art_drop_purchase_keys(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Product->value,
            'title' => 'CMS Digital Pack',
            'slug' => 'cms-digital-pack',
            'purchase_key' => 'cms-digital-pack',
            'metadata' => [
                'product_kind' => 'digital',
                'price_cents' => 1200,
            ],
        ]);
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Drop->value,
            'title' => 'CMS Art Drop',
            'slug' => 'cms-art-drop',
            'purchase_key' => 'cms-art-drop',
            'metadata' => [
                'drop_kind' => 'content',
                'price_cents' => 8600,
            ],
        ]);

        $this->get('/store')
            ->assertOk()
            ->assertSee('Work in Progress')
            ->assertDontSee('data-detail="cms-digital-pack"', false)
            ->assertDontSee('data-detail="cms-art-drop"', false);

        $this->getJson(route('public-content.payload', 'store'))
            ->assertOk()
            ->assertJsonPath('products.0.key', 'cms-art-drop')
            ->assertJsonPath('products.1.key', 'cms-digital-pack')
            ->assertJsonPath('products.0.category', 'merch')
            ->assertJsonPath('products.1.category', 'music');
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

    /**
     * @param  array<int, string>  $productKeys
     */
    private function createPendingPayPalOrder(string $identifier, array $productKeys, string $orderId): void
    {
        $this->fakeCreatedOrder($orderId);

        $response = $this->postJson('/checkout/paypal/orders', [
            'identifier' => $identifier,
            'customer_name' => 'Reny Fan',
            'customer_email' => 'fan@renyrenteria.com',
            'customer_phone' => '+50760000005',
            'customer_country' => 'Panama',
            'product_keys' => $productKeys,
            'currency' => 'USD',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('status', 'created')
            ->assertJsonPath('paypal_order_id', $orderId);
    }

    private function fakeSuccessfulCapture(string $orderId, string $amount, string $captureId = 'CAPTURE-100'): void
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
                                    'id' => $captureId,
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
