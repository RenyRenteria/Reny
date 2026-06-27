<?php

namespace Tests\Feature;

use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class StorePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_store_page_matches_four_slot_mockup_for_guest(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('Get your');
        $response->assertSee('Royal Pass');
        $response->assertSee('Get Your Royal Pass');
        $response->assertSee('Reny Renteria en Concierto');
        $response->assertSee('Festival de la Rosa Dorada');
        $response->assertSee('Work in Progress');
        $response->assertSee('Crown Collection');
        $response->assertSee('FREE');
        $response->assertSee('$15');
        $response->assertSee('GET TICKETS');
        $response->assertSee('GET DELUXE');
        $response->assertSee('GET MERCH');
        $response->assertSee('class="storefront-countdown"', false);
        $response->assertSee('data-countdown-at="2026-09-21T19:30:00-05:00"', false);
        $response->assertSee('data-countdown-at="2026-12-19T19:30:00-05:00"', false);
        $response->assertSee('images/store/reny-concert.png');
        $response->assertSee('images/store/rosa-dorada.png');
        $response->assertSee('images/store/work-in-progress.png');
        $response->assertSee('images/store/crown-collection.png');
        $response->assertSee('Selected currency is a reference. PayPal checkout is charged in USD.');
        $response->assertSee('data-free-event-rsvp="concert"', false);
        $response->assertSee('data-buy="listening"', false);
        $response->assertSee('data-royal-pass-option="royal"', false);
        $response->assertSee('data-requires-plan-selection="true"', false);
        $response->assertSee('data-buy="deluxe"', false);
        $response->assertSee('data-buy="merch"', false);
        $response->assertSee('data-buy-image="'.asset('images/store/rosa-dorada.png').'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'listening']).'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'deluxe']).'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'merch']).'"', false);
        $response->assertDontSee('data-buy="concert"', false);
        $response->assertSee('PayPal Checkout');
        $response->assertSee('id="paypalButtons"', false);
        $response->assertDontSee('Load PayPal checkout');
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.url('/store').'"', false);
        $response->assertSee('data-payment-method="paypal"', false);
        $response->assertSee('role="radio"', false);
        $response->assertSee('data-free-event-rsvp-endpoint="'.route('community.free-event-rsvp.store').'"', false);
        $response->assertSee('id="freeEventRsvpForm"', false);
        $response->assertSee('Correo electrónico');
        $response->assertSee('País');

        $html = $response->getContent();

        $this->assertSame(4, substr_count($html, 'storefront-card'));
        $this->assertSame(2, substr_count($html, 'storefront-countdown'));
        $this->assertLessThan(strpos($html, 'Reny Renteria en Concierto'), strpos($html, 'Royal Pass'));
        $this->assertLessThan(strpos($html, 'Festival de la Rosa Dorada'), strpos($html, 'Reny Renteria en Concierto'));
        $this->assertStringNotContainsString('is-event-secondary', $html);
        $this->assertStringNotContainsString('Official store', $html);
        $this->assertStringNotContainsString('Reny Shop', $html);
        $this->assertStringNotContainsString('data-filter=', $html);
        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringContainsString('class="store-royal-pass-selector"', $html);
        $this->assertStringNotContainsString('role="button"', $html);
        $this->assertStringNotContainsString('aria-selected', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
    }

    public function test_store_page_hides_royal_pass_banner_for_logged_in_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/store')
            ->assertOk()
            ->assertDontSee('class="store-royal-pass"', false)
            ->assertDontSee('Get Your Royal Pass')
            ->assertSee('Reny Renteria en Concierto');
    }

    public function test_checkout_screen_renders_product_details_and_modal_hooks(): void
    {
        $response = $this->get(route('store.checkout', ['product' => 'listening']));

        $response
            ->assertOk()
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Rock &amp; Folk Pty, Ciudad de Panama', false)
            ->assertSee('$15')
            ->assertSee('images/store/rosa-dorada.png')
            ->assertSee('data-buy="listening"', false)
            ->assertSee('data-buy-image="'.asset('images/store/rosa-dorada.png').'"', false)
            ->assertSee('data-buy-price-value="15.00"', false)
            ->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'listening']).'"', false)
            ->assertSee('data-auto-open-checkout="true"', false)
            ->assertSee('data-copy-url="'.route('store.checkout', ['product' => 'listening']).'"', false)
            ->assertSee('id="bagLayer"', false)
            ->assertSee('id="paypalButtons"', false)
            ->assertSee('PayPal Checkout')
            ->assertSee('id="nameField"', false)
            ->assertSee('id="emailField"', false)
            ->assertSee('id="phoneField"', false)
            ->assertSee('id="countryField"', false)
            ->assertSee('pattern="^\+[1-9][0-9]{6,14}$"', false)
            ->assertSee('Select country')
            ->assertSee('data-payment-method="paypal"', false)
            ->assertSee('data-create-order-endpoint="'.route('checkout.paypal.orders').'"', false)
            ->assertDontSee('Load PayPal checkout')
            ->assertSee('GET TICKETS');
    }

    public function test_checkout_screen_uses_published_event_image_for_page_and_modal_data(): void
    {
        $asset = MediaAsset::create([
            'type' => MediaAssetType::Image->value,
            'title' => 'CMS checkout poster',
            'disk' => 'public',
            'path' => 'store/cms-checkout-poster.png',
            'original_filename' => 'cms-checkout-poster.png',
            'mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 100,
            'is_public' => true,
            'alt_text' => 'CMS checkout poster',
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);

        SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => [
                'slots' => [
                    'event_secondary' => [
                        'product_key' => 'listening',
                        'image_asset_id' => $asset->id,
                    ],
                ],
            ],
            'published_at' => now(),
        ]);

        $assetUrl = $asset->publicUrl();

        $this->get(route('store.checkout', ['product' => 'listening']))
            ->assertOk()
            ->assertSee('src="'.$assetUrl.'"', false)
            ->assertSee('alt="CMS checkout poster"', false)
            ->assertSee('data-buy-image="'.$assetUrl.'"', false);

        $this->get('/store')
            ->assertOk()
            ->assertSee('data-buy-image="'.$assetUrl.'"', false);
    }

    public function test_checkout_frontend_opens_paypal_without_redundant_button(): void
    {
        $js = $this->frontendJavaScriptSource();

        $this->assertStringContainsString('startCheckoutFromBuyButton(button);', $js);
        $this->assertStringContainsString("startCheckoutFromBuyButton(button, { source: 'shareable_checkout' });", $js);
        $this->assertStringContainsString("openCheckoutModal(autoOpenButton.dataset.buy, { source: 'dedicated_checkout_url' })", $js);
        $this->assertStringContainsString('initializeVisiblePayPalCheckout();', $js);
        $this->assertStringContainsString("image.className = 'store-bag-image';", $js);
        $this->assertStringContainsString('customer_name', $js);
        $this->assertStringContainsString('customer_country', $js);
        $this->assertStringContainsString('Add a valid international phone number.', $js);
        $this->assertStringNotContainsString('completePurchaseButton', $js);
        $this->assertStringNotContainsString('Load PayPal checkout', $js);
    }

    public function test_store_checkout_rehydrates_after_persistent_public_navigation(): void
    {
        $js = $this->frontendJavaScriptSource();

        $this->assertStringContainsString('const initializeStoreInteractions = (root = document) => {', $js);
        $this->assertStringContainsString('initializeStoreInteractions(root);', $js);
        $this->assertStringContainsString('scope.querySelector?.(\'[data-buy]\')', $js);
        $this->assertStringContainsString('document.getElementById(\'openBag\')?.addEventListener', $js);
        $this->assertStringContainsString('document.querySelectorAll(\'[data-close]\')', $js);
        $this->assertStringContainsString('paymentButtons.forEach((button) =>', $js);
        $this->assertStringContainsString('window.renyStoreKeydownAbort?.abort();', $js);
    }

    public function test_checkout_screen_rejects_unknown_product(): void
    {
        $this->get('/store/checkout/not-real')
            ->assertNotFound();
    }

    public function test_store_event_images_use_matching_ratio_rules(): void
    {
        $css = $this->frontendCssSource();

        $this->assertStringContainsString('.storefront-card.is-event .storefront-image', $css);
        $this->assertStringContainsString('aspect-ratio: 245 / 301;', $css);
        $this->assertStringContainsString('object-fit: contain;', $css);
        $this->assertStringNotContainsString('storefront-card.is-event-secondary .storefront-image', $css);
        $this->assertStringNotContainsString('aspect-ratio: 1;'."\n    }\n\n    .storefront-copy", $css);
    }

    public function test_existing_tabs_link_to_store_route(): void
    {
        foreach (['/', '/videos', '/photos', '/community'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.url('/store').'"', false)
                ->assertDontSee('href="#store"', false);
        }
    }
}
