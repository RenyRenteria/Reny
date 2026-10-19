<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\YoycolFulfillment;
use App\Models\YoycolProduct;
use App\Models\YoycolQuote;
use App\Services\Yoycol\YoycolCatalog;
use App\Services\Yoycol\YoycolCheckout;
use App\Services\Yoycol\YoycolClient;
use App\Services\Yoycol\YoycolFulfillmentService;
use App\Services\Yoycol\YoycolPaymentRecovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class YoycolCommerceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'yoycol.enabled' => true, 'yoycol.access_key' => 'test-access', 'yoycol.secret_key' => 'test-secret',
            'services.paypal.base_url' => 'https://paypal.test', 'services.paypal.client_id' => 'client', 'services.paypal.client_secret' => 'secret',
            'admin.cms_enabled' => true,
        ]);
        Http::preventStrayRequests();
    }

    public function test_hmac_signature_uses_sorted_raw_query_values_and_no_body(): void
    {
        Http::fake(['www.yoycol.com/*' => Http::response(['code' => '100000', 'data' => []])]);
        app(YoycolClient::class)->request('GET', 'catalog/products', ['size' => 2, 'keyword' => 'hello world']);
        Http::assertSent(function ($request): bool {
            $signature = "method=GET\npath=/api/2025/open/v4/catalog/products\ntimestamp=".$request->header('X-API-Timestamp')[0]
                ."\nnonce=".$request->header('X-API-Nonce')[0]."\naccessKey=test-access\nalgorithm=HmacSHA256\nversion=4.0\nparams=keyword=hello world&size=2";

            return $request->header('X-API-Signature')[0] === base64_encode(hash_hmac('sha256', $signature, 'test-secret', true));
        });
    }

    public function test_import_creates_drafts_preserves_merchant_prices_and_imports_shoe_size_attributes(): void
    {
        $this->fakeCatalog();
        $catalog = app(YoycolCatalog::class);
        $this->assertSame(1, $catalog->sync());
        $product = YoycolProduct::firstOrFail();
        $this->assertFalse($product->published);
        $this->assertNull($product->price_cents);
        $this->assertSame('White / US7(EUR40)', $product->variants[0]['label']);
        $product->update(['title' => 'Royal shoes', 'published' => true, 'price_cents' => 6200]);
        $catalog->sync();
        $this->assertSame('Royal shoes', $product->fresh()->title);
        $this->assertSame(6200, $product->fresh()->price_cents);
        $this->assertTrue($product->fresh()->published);
    }

    public function test_unavailable_deleted_templates_are_unpublished_only_after_successful_sync(): void
    {
        $product = $this->product();
        Http::fake(['www.yoycol.com/*' => Http::response(['code' => '100000', 'data' => ['records' => []]])]);
        app(YoycolCatalog::class)->sync();
        $this->assertFalse($product->fresh()->published);
    }

    public function test_drafts_disabled_and_stale_products_cannot_be_bought_or_leaked_in_store(): void
    {
        $product = $this->product(['published' => false]);
        $this->get('/merch')->assertOk()->assertDontSee($product->title);
        $this->get('/store/checkout/'.$product->productKey())->assertNotFound();
        $product->update(['published' => true, 'synced_at' => now()->subDays(2)]);
        $this->get('/store/checkout/'.$product->productKey())->assertNotFound();
        $product->update(['synced_at' => now()]);
        config(['yoycol.enabled' => false]);
        $this->get('/store/checkout/'.$product->productKey())->assertNotFound();
        $this->postJson(route('yoycol.quote', $product), $this->addressPayload())->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_published_product_is_in_merch_and_uses_dedicated_size_checkout(): void
    {
        $product = $this->product();
        $this->get('/merch')->assertOk()->assertSee($product->title)->assertSee('CHOOSE SIZE');
        $this->get('/store/checkout/'.$product->productKey())->assertOk()->assertSee('Size / color')->assertSee('Delivery phone');
    }

    public function test_shipping_uses_verified_panama_region_and_quantity_without_trusting_client_prices(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $quote = $this->postJson(route('yoycol.quote', $product), [...$this->addressPayload(), 'quantity' => 3, 'amount_cents' => 1])
            ->assertOk()->assertJsonPath('subtotal_cents', 18000)->assertJsonPath('shipping_cents', 1600)->assertJsonPath('amount_cents', 19600)->json();
        $row = YoycolQuote::findOrFail($quote['quote_id']);
        $this->assertSame('PA', $row->snapshot['address']['country']);
        $this->assertStringNotContainsString('Test Street', $row->getRawOriginal('snapshot'));
    }

    public function test_invalid_size_country_and_unavailable_shipping_never_reach_payment(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $this->postJson(route('yoycol.quote', $product), [...$this->addressPayload(), 'variant_id' => 'unknown'])->assertUnprocessable();
        $data = $this->addressPayload();
        $data['address']['country'] = 'XX';
        $this->postJson(route('yoycol.quote', $product), $data)->assertUnprocessable();
        $this->postJson(route('yoycol.quote', $product), [...$this->addressPayload(), 'shipping_level' => 'unavailable'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_missing_expired_foreign_and_reused_quotes_cannot_create_payment(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $payload = $this->checkoutPayload($product);
        $this->postJson('/checkout/paypal/orders', $payload)->assertUnprocessable();
        $id = $this->quote($product);
        $payload['yoycol_quote_id'] = $id;
        $row = YoycolQuote::find($id);
        $hash = $row->session_hash;
        $row->update(['session_hash' => str_repeat('0', 64)]);
        $this->postJson('/checkout/paypal/orders', $payload)->assertUnprocessable();
        $row->update(['session_hash' => $hash, 'expires_at' => now()->subMinute()]);
        $this->postJson('/checkout/paypal/orders', $payload)->assertUnprocessable();
        $row->update(['expires_at' => now()->addMinutes(10)]);
        $this->postJson('/checkout/paypal/orders', $payload)->assertOk();
        $this->postJson('/checkout/paypal/orders', $payload)->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
    }

    public function test_price_and_design_changes_require_requoting(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $id = $this->quote($product);
        $product->update(['price_cents' => 6500]);
        $this->postJson('/checkout/paypal/orders', [...$this->checkoutPayload($product), 'yoycol_quote_id' => $id])->assertUnprocessable();
        $product->update(['price_cents' => 6000, 'design_code' => 'changed']);
        $this->postJson('/checkout/paypal/orders', [...$this->checkoutPayload($product), 'yoycol_quote_id' => $id])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_mixed_bags_and_local_payment_are_rejected_for_yoycol_merch(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $payload = [...$this->checkoutPayload($product), 'yoycol_quote_id' => $this->quote($product)];
        $this->postJson('/checkout/paypal/orders', [...$payload, 'product_keys' => [$product->productKey(), $product->productKey()]])->assertUnprocessable();
        $this->postJson('/checkout/local', [...$payload, 'local_reference' => 'BANK123456'])->assertUnprocessable();
        $this->assertDatabaseCount('yoycol_fulfillments', 0);
    }

    public function test_paid_checkout_submits_one_correct_order_and_tracks_supplier_payment_separately(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $payload = [...$this->checkoutPayload($product), 'yoycol_quote_id' => $this->quote($product)];
        $this->postJson('/checkout/paypal/orders', $payload)->assertOk();
        $fulfillment = YoycolFulfillment::firstOrFail();
        $service = app(YoycolFulfillmentService::class);
        $service->process($fulfillment);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v4/orders'));
        $this->postJson('/checkout/paypal', [...$payload, 'paypal_order_id' => 'PAYPAL-YOYCOL'])->assertOk();
        $this->artisan('yoycol:sync')->assertSuccessful();
        $this->assertSame('payment_required', $fulfillment->fresh()->state);
        $this->assertSame('completed', $fulfillment->order->fresh()->status);
        $service->process($fulfillment);
        $writes = Http::recorded(fn ($r) => $r->method() === 'POST' && str_ends_with($r->url(), '/v4/orders'));
        $this->assertCount(1, $writes);
        $body = $writes->values()[0][0]->data();
        $this->assertSame('DESIGN-1', $body['items'][0]['designCode']);
        $this->assertSame('SKU-SHOE-40', $body['items'][0]['skuCode']);
        $this->assertSame('PA', $body['address']['country']);
        $this->assertSame('standard', $body['shippingLevelCode']);
        $this->assertFalse($body['autoSwitchShippingLevel']);
        $this->assertStringNotContainsString('Test Street', $fulfillment->getRawOriginal('request_payload'));
        Http::assertSent(fn ($r) => $r->url() === 'https://paypal.test/v2/checkout/orders' && $r['purchase_units'][0]['amount']['value'] === '68.00');
        Http::assertSent(fn ($r) => $r->url() === 'https://paypal.test/v2/checkout/orders'
            && $r['purchase_units'][0]['amount']['breakdown']['shipping']['value'] === '8.00'
            && $r['purchase_units'][0]['amount']['breakdown']['item_total']['value'] === '60.00'
            && $r['purchase_units'][0]['shipping']['address']['country_code'] === 'PA'
            && $r['payment_source']['paypal']['experience_context']['shipping_preference'] === 'SET_PROVIDED_ADDRESS');
    }

    public function test_ambiguous_submission_is_reconciled_without_duplicate_writes(): void
    {
        $fulfillment = $this->paidFulfillment();
        $posts = 0;
        Http::fake(function ($request) use ($fulfillment, &$posts) {
            if ($request->method() === 'POST') {
                $posts++;

                return Http::response('gateway timeout', 504);
            }
            if (str_contains($request->url(), '/orders?')) {
                return $this->ok(['records' => str_contains($request->url(), 'page=1&') ? [$this->summary($fulfillment->store_order_sn)] : []]);
            }

            return str_ends_with($request->url(), '/tracking') ? $this->ok([]) : $this->ok(['order' => $this->summary($fulfillment->store_order_sn)]);
        });
        $service = app(YoycolFulfillmentService::class);
        $service->process($fulfillment);
        $this->assertSame('review', $fulfillment->fresh()->state);
        $service->process($fulfillment);
        $this->assertSame('payment_required', $fulfillment->fresh()->state);
        $this->assertSame(1, $posts);
    }

    public function test_absent_ambiguous_order_is_never_automatically_resubmitted(): void
    {
        $fulfillment = $this->paidFulfillment(['attempted_at' => now(), 'state' => 'submitting']);
        Http::fake(['www.yoycol.com/*' => $this->ok(['records' => []])]);
        app(YoycolFulfillmentService::class)->process($fulfillment);
        app(YoycolFulfillmentService::class)->process($fulfillment);
        $this->assertSame('review', $fulfillment->fresh()->state);
        Http::assertNotSent(fn ($r) => $r->method() !== 'GET');
    }

    public function test_paid_at_and_tracking_update_fulfillment_without_changing_customer_payment(): void
    {
        $fulfillment = $this->paidFulfillment(['provider_order_id' => '900', 'attempted_at' => now()]);
        Http::fake(fn ($r) => str_ends_with($r->url(), '/tracking')
            ? $this->ok(['waybillCode' => 'TRACK123', 'lpName' => 'Carrier', 'headTrackingEvents' => [['description' => 'In transit']]])
            : $this->ok(['order' => [...$this->summary($fulfillment->store_order_sn), 'canPay' => false, 'paidAt' => now()->toIso8601String()]]));
        app(YoycolFulfillmentService::class)->process($fulfillment);
        $this->assertSame('shipped', $fulfillment->fresh()->state);
        $this->assertSame('completed', $fulfillment->order->status);
        $this->actingAs($fulfillment->order->user)->get(route('yoycol.tracking', $fulfillment->order))->assertOk()->assertSee('TRACK123')->assertDontSee('payment_required');
        $this->actingAs(User::factory()->create())->get(route('yoycol.tracking', $fulfillment->order))->assertNotFound();
    }

    public function test_refunds_prevent_fulfillment_and_require_supplier_review(): void
    {
        $fulfillment = $this->paidFulfillment();
        $fulfillment->order->update(['refund_amount_cents' => 100]);
        app(YoycolFulfillmentService::class)->process($fulfillment);
        $this->assertSame('refund_review', $fulfillment->fresh()->state);
        Http::assertNothingSent();
    }

    public function test_provider_reference_mismatch_does_not_attach_unrelated_order(): void
    {
        $fulfillment = $this->paidFulfillment();
        Http::fake(['www.yoycol.com/*' => $this->ok($this->summary('OTHER-ORDER'))]);
        app(YoycolFulfillmentService::class)->process($fulfillment);
        $this->assertNull($fulfillment->fresh()->provider_order_id);
        $this->assertSame('review', $fulfillment->fresh()->state);
    }

    public function test_only_publishing_admin_can_manage_catalog_and_orders(): void
    {
        $product = $this->product(['published' => false]);
        $this->get(route('admin.yoycol.index'))->assertRedirect();
        $this->actingAs(User::factory()->create())->get(route('admin.yoycol.index'))->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN]))->withSession(['admin_authenticated_at' => now()->timestamp]);
        $this->get(route('admin.yoycol.index'))->assertOk()->assertSee('Sincronizar diseños');
        $this->patch(route('admin.yoycol.update', $product), ['title' => 'Royal shoes', 'published' => 1])->assertSessionHasErrors('published');
        $this->patch(route('admin.yoycol.update', $product), ['title' => 'Royal shoes', 'published' => 1, 'price' => '75.99'])->assertRedirect();
        $this->assertSame(7599, $product->fresh()->price_cents);
        $this->assertTrue($product->fresh()->published);
    }

    public function test_capture_started_merch_cannot_be_cancelled_even_before_provider_response(): void
    {
        $this->fakeProviders();
        $product = $this->product();
        $this->postJson('/checkout/paypal/orders', [...$this->checkoutPayload($product), 'yoycol_quote_id' => $this->quote($product)])->assertOk();
        app(YoycolCheckout::class)->beginCapture(collect([Order::firstOrFail()]));
        $this->postJson('/checkout/paypal/orders/cancel', ['paypal_order_id' => 'PAYPAL-YOYCOL'])->assertUnprocessable();
        $this->assertSame('pending', Order::firstOrFail()->status);
    }

    public function test_manual_retry_reconciles_later_short_pages_and_does_not_duplicate(): void
    {
        $fulfillment = $this->paidFulfillment(['attempted_at' => now(), 'state' => 'review']);
        Http::fake(function ($request) use ($fulfillment) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY) ?? '', $query);
            if (isset($query['page'])) {
                return $this->ok(['records' => match ((int) $query['page']) {
                    1 => [$this->summary('UNRELATED')],
                    2 => [$this->summary($fulfillment->store_order_sn)],
                    default => [],
                }]);
            }

            return str_ends_with($request->url(), '/tracking') ? $this->ok([]) : $this->ok(['order' => $this->summary($fulfillment->store_order_sn)]);
        });
        app(YoycolFulfillmentService::class)->retry($fulfillment);
        $this->assertSame('900', $fulfillment->fresh()->provider_order_id);
        Http::assertNotSent(fn ($r) => $r->method() === 'POST');
    }

    public function test_supplier_cancellation_does_not_mark_customer_payment_refunded(): void
    {
        $fulfillment = $this->paidFulfillment(['provider_order_id' => '900']);
        Http::fake(fn ($r) => $r->method() === 'PUT'
            ? Http::response(['code' => '100000', 'data' => 'success'])
            : $this->ok(['order' => $this->summary($fulfillment->store_order_sn)]));
        app(YoycolFulfillmentService::class)->cancel($fulfillment);
        $this->assertSame('cancelled', $fulfillment->fresh()->state);
        $this->assertSame('completed', $fulfillment->order->fresh()->status);
        Http::assertSent(fn ($r) => $r->method() === 'PUT' && str_ends_with($r->url(), '/900/cancel'));
    }

    public function test_lost_capture_response_is_recovered_with_read_only_paypal_lookup_once(): void
    {
        $fulfillment = $this->paidFulfillment();
        $order = $fulfillment->order;
        $order->update(['status' => 'pending', 'provider_capture_id' => null, 'metadata' => [
            'checkout' => ['capture_started' => true, 'paypal_order_id' => 'PP-RECOVERY'],
            'product' => ['title' => 'Shoes', 'kind' => 'physical', 'source_type' => 'yoycol_product'],
        ]]);
        Http::fake(fn ($r) => str_contains($r->url(), '/oauth2/token')
            ? Http::response(['access_token' => 'test-token'])
            : Http::response(['id' => 'PP-RECOVERY', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'RECOVERED', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '68.00'],
            ]]]]]]));
        $recovery = app(YoycolPaymentRecovery::class);
        $recovery->recover($order);
        $until = $order->fresh()->royal_granted_until->toIso8601String();
        $recovery->recover($order->fresh());
        $this->assertSame('completed', $order->fresh()->status);
        $this->assertSame('RECOVERED', $order->fresh()->provider_capture_id);
        $this->assertSame($until, $order->fresh()->royal_granted_until->toIso8601String());
        Http::assertNotSent(fn ($r) => str_contains($r->url(), '/capture'));
    }

    public function test_payment_recovery_refuses_wrong_amounts_and_never_submits_to_supplier(): void
    {
        $fulfillment = $this->paidFulfillment();
        $order = $fulfillment->order;
        $order->update(['status' => 'pending', 'provider_capture_id' => null, 'metadata' => [
            'checkout' => ['capture_started' => true, 'paypal_order_id' => 'PP-RECOVERY'],
        ]]);
        Http::fake(fn ($r) => str_contains($r->url(), '/oauth2/token')
            ? Http::response(['access_token' => 'test-token'])
            : Http::response(['id' => 'PP-RECOVERY', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [[
                'id' => 'RECOVERED', 'status' => 'COMPLETED', 'amount' => ['currency_code' => 'USD', 'value' => '1.00'],
            ]]]]]]));
        app(YoycolPaymentRecovery::class)->recover($order);
        $this->assertSame('pending', $order->fresh()->status);
        $this->assertNotNull($fulfillment->fresh()->last_error);
        Http::assertNotSent(fn ($r) => str_contains($r->url(), 'yoycol.com'));
    }

    private function product(array $attributes = []): YoycolProduct
    {
        return YoycolProduct::create([...[
            'template_id' => '1', 'design_code' => 'DESIGN-1', 'title' => 'Royal leather shoes',
            'price_cents' => 6000, 'published' => true, 'synced_at' => now(),
            'variants' => [['id' => '40', 'sku' => 'SKU-SHOE-40', 'label' => 'White / US7(EUR40)', 'cost_cents' => 2600, 'image_url' => null]],
        ], ...$attributes]);
    }

    private function addressPayload(): array
    {
        return ['variant_id' => '40', 'quantity' => 1, 'address' => [
            'firstName' => 'Test', 'lastName' => 'Buyer', 'email' => 'buyer@example.test', 'phone' => '+50760000000',
            'country' => 'PA', 'province' => 'Panama', 'city' => 'Panama', 'address1' => 'Test Street 123', 'zip' => '0801',
        ]];
    }

    private function checkoutPayload(YoycolProduct $product): array
    {
        return ['identifier' => 'buyer@example.test', 'customer_name' => 'Test Buyer', 'customer_email' => 'buyer@example.test',
            'customer_phone' => '+50760000000', 'customer_country' => 'PA', 'product_keys' => [$product->productKey()], 'currency' => 'USD'];
    }

    private function quote(YoycolProduct $product): string
    {
        return $this->postJson(route('yoycol.quote', $product), $this->addressPayload())->assertOk()->json('quote_id');
    }

    private function ok(array $data)
    {
        return Http::response(['code' => '100000', 'data' => $data]);
    }

    private function summary(string $reference): array
    {
        return ['orderId' => 900, 'orderNo' => 'Y900', 'storeOrderSn' => $reference, 'canPay' => true, 'canCancel' => true, 'status' => 'unpaid', 'currency' => 'USD'];
    }

    private function paidFulfillment(array $attributes = []): YoycolFulfillment
    {
        $order = Order::create(['user_id' => User::factory()->create()->id, 'provider' => 'paypal', 'provider_order_id' => 'PAID-1', 'provider_capture_id' => 'CAPTURE-1', 'product_key' => 'yoycol-1', 'amount_cents' => 6800, 'currency' => 'USD', 'status' => 'completed']);

        return YoycolFulfillment::create(['order_id' => $order->id, 'store_order_sn' => 'RENY-TEST', 'request_payload' => ['storeOrderSn' => 'RENY-TEST'], ...$attributes]);
    }

    private function fakeCatalog(): void
    {
        Http::fake(fn ($r) => str_contains($r->url(), 'product_templates?')
            ? $this->ok(['records' => [['id' => 1, 'productName' => 'Shoes']]])
            : $this->ok(['id' => 1, 'designCode' => 'DESIGN-1', 'variants' => [[
                'variantId' => 40, 'skuCode' => 'SKU-SHOE-40', 'price' => 26, 'currency' => 'USD', 'size' => null,
                'attributes' => [['valueText' => 'White'], ['valueText' => 'US7(EUR40)']],
            ]]]));
    }

    private function fakeProviders(): void
    {
        Http::fake(function ($r) {
            if (str_contains($r->url(), 'sku_quotes')) {
                return $this->ok([['regionName' => 'A area', 'levels' => [
                    ['levelCode' => 'standard', 'levelName' => 'Standard', 'firstPrice' => 8, 'additionalPrice' => 4],
                    ['levelCode' => 'unavailable', 'levelName' => 'Express', 'firstPrice' => null, 'additionalPrice' => null],
                ]]]);
            }
            if (str_contains($r->url(), '/oauth2/token')) {
                return Http::response(['access_token' => 'test-token']);
            }
            if (str_ends_with($r->url(), '/capture')) {
                return Http::response(['id' => 'PAYPAL-YOYCOL', 'status' => 'COMPLETED', 'purchase_units' => [['payments' => ['captures' => [['id' => 'CAPTURE-YOYCOL', 'status' => 'COMPLETED', 'amount' => ['value' => '68.00', 'currency_code' => 'USD']]]]]]]);
            }
            if (str_contains($r->url(), 'paypal.test')) {
                return Http::response(['id' => 'PAYPAL-YOYCOL']);
            }
            $reference = YoycolFulfillment::firstOrFail()->store_order_sn;
            if ($r->method() === 'POST') {
                return $this->ok($this->summary($reference));
            }

            return str_ends_with($r->url(), '/tracking') ? $this->ok([]) : $this->ok(['order' => $this->summary($reference)]);
        });
    }
}
