<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\SitePageSetting;
use App\Models\User;
use App\Services\StorefrontSettingsService;
use Carbon\CarbonImmutable;
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
        $this->travelTo(CarbonImmutable::parse('2026-08-14 13:00:00', 'America/Panama'));
    }

    public function test_store_page_hides_removed_concert_and_renders_remaining_slots_for_guest(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('class="golden-stage-page checkout-page store-stage-page"', false);
        $response->assertSee('class="store-shell home-shell golden-stage-shell store-stage-shell"', false);
        $response->assertSee(asset('images/reny-renteria-logo-white.png'), false);
        $response->assertSee('class="stage-lights"', false);
        $response->assertSee('Get your');
        $response->assertSee('Royal Pass');
        $response->assertSee('Unlock Royal Pass');
        $response->assertDontSee('Reny Renteria en Concierto');
        $response->assertSee('Festival de la Rosa Dorada');
        $response->assertSee('Work in Progress');
        $response->assertSee('Crown Collection');
        $response->assertDontSee('FREE');
        $response->assertSee('$15');
        $response->assertSee('GET TICKETS');
        $response->assertSee('LISTEN');
        $response->assertSee('GET MERCH');
        $response->assertSee('class="storefront-countdown"', false);
        $response->assertDontSee('data-countdown-at="2026-09-21T19:30:00-05:00"', false);
        $response->assertSee('data-countdown-at="2026-12-16T19:30:00-05:00"', false);
        $response->assertDontSee('images/store/reny-concert.png');
        $response->assertSee('images/store/rosa-dorada.png');
        $response->assertSee('images/store/crown-collection.png');
        $response->assertSee('images/store/royal-pass.png');
        $response->assertSee('PayPal charges in USD. Every completed purchase activates Royal Pass for 1 month on this account.');
        $response->assertDontSee('data-free-event-rsvp="concert"', false);
        $response->assertSee('data-buy="listening"', false);
        $response->assertSee('data-royal-pass-option="royal"', false);
        $response->assertSee('data-royal-pass-selected="true"', false);
        $response->assertSee('aria-pressed="true"', false);
        $response->assertSee('aria-disabled="false"', false);
        $response->assertSee('data-buy-image="'.asset('images/store/royal-pass.png').'"', false);
        $response->assertDontSee('data-buy="deluxe"', false);
        $response->assertSee('href="'.url('/music').'"', false);
        $response->assertSee('data-buy="merch"', false);
        $response->assertSee('data-buy-image="'.asset('images/store/rosa-dorada.png').'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'listening']).'"', false);
        $response->assertDontSee(route('store.checkout', ['product' => 'deluxe']), false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'merch']).'"', false);
        $response->assertDontSee('data-buy="concert"', false);
        $response->assertSee('Complete your order');
        $response->assertSee('Name + email only');
        $response->assertSee('id="paypalButtons"', false);
        $response->assertDontSee('Load PayPal checkout');
        $response->assertSee('href="'.url('/store').'"', false);
        $response->assertDontSee('data-payment-method="paypal"', false);
        $response->assertDontSee('role="radiogroup"', false);
        $response->assertSee('data-free-event-rsvp-endpoint="'.route('community.free-event-rsvp.store').'"', false);
        $response->assertSee('id="freeEventRsvpForm"', false);
        $response->assertSee('Correo electrónico');
        $response->assertSee('País');

        $html = $response->getContent();

        preg_match_all('/<nav[^>]+aria-label="(?:Main|Mobile) menu"[^>]*>(.*?)<\/nav>/s', $html, $matches);

        $this->assertCount(2, $matches[1]);

        foreach ($matches[1] as $navHtml) {
            $this->assertStringNotContainsString('href="'.route('store').'"', $navHtml);
        }

        $this->assertSame(3, substr_count($html, 'storefront-card'));
        $this->assertSame(1, substr_count($html, 'storefront-countdown'));
        $this->assertLessThan(strpos($html, 'Festival de la Rosa Dorada'), strpos($html, 'Royal Pass'));
        $this->assertStringNotContainsString('is-event-secondary', $html);
        $this->assertStringNotContainsString('Official store', $html);
        $this->assertStringNotContainsString('Reny Shop', $html);
        $this->assertStringNotContainsString('data-filter=', $html);
        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringContainsString('class="home-royal-pass is-selected"', $html);
        $this->assertStringContainsString('data-royal-pass-banner', $html);
        $this->assertStringNotContainsString('home-royal-pass-images', $html);
        $this->assertStringNotContainsString('data-requires-plan-selection="true"', $html);
        $this->assertStringNotContainsString('role="button"', $html);
        $this->assertStringNotContainsString('aria-selected', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
        $this->assertStringContainsString('deluxe album', strtolower($html));
    }

    public function test_store_page_keeps_royal_pass_banner_for_free_accounts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/store')
            ->assertOk()
            ->assertSee('data-royal-pass-banner', false)
            ->assertSee('Unlock Royal Pass')
            ->assertDontSee('Reny Renteria en Concierto');
    }

    public function test_store_page_hides_royal_pass_banner_for_active_royal_accounts(): void
    {
        $this->actingAs(User::factory()->royal()->create())
            ->get('/store')
            ->assertOk()
            ->assertDontSee('data-royal-pass-banner', false)
            ->assertDontSee('Unlock Royal Pass')
            ->assertDontSee('Reny Renteria en Concierto');
    }

    public function test_shows_page_lists_all_upcoming_shows_once_in_chronological_order(): void
    {
        $response = $this->get(route('shows'));

        $response
            ->assertOk()
            ->assertSee('<title>Shows | Reny Renteria</title>', false)
            ->assertSee('data-analytics-screen="shows"', false)
            ->assertSee('class="golden-stage-page checkout-page store-stage-page"', false)
            ->assertSee('class="store-shell home-shell golden-stage-shell store-stage-shell"', false)
            ->assertSee(asset('images/reny-renteria-logo-white.png'), false)
            ->assertSee('class="stage-lights"', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('data-free-event-rsvp="concert"', false)
            ->assertSee('data-buy="listening"', false)
            ->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'listening']).'"', false)
            ->assertSee('id="paypalButtons"', false)
            ->assertDontSee('Crown Collection')
            ->assertDontSee('data-buy="merch"', false)
            ->assertSee('data-royal-pass-banner', false)
            ->assertDontSee('home-royal-pass-images', false)
            ->assertSee('class="tab is-active" href="'.route('shows').'" aria-current="page"', false);

        $html = $response->getContent();

        $this->assertSame(2, substr_count($html, 'class="home-show-card"'));
        $this->assertSame(0, substr_count($html, 'storefront-card'));
        $this->assertSame(1, substr_count($html, '<h2>Reny Renteria en Concierto</h2>'));
        $this->assertLessThan(strpos($html, 'Festival de la Rosa Dorada'), strpos($html, 'Reny Renteria en Concierto'));
    }

    public function test_shows_page_uses_the_home_show_card_structure_and_segmented_countdown(): void
    {
        $html = $this->get(route('shows'))
            ->assertOk()
            ->assertSee('class="home-show-image"', false)
            ->assertSee('class="home-show-copy"', false)
            ->assertSee('class="home-event-countdown"', false)
            ->assertSee('data-countdown-display="segments"', false)
            ->assertSee('class="home-show-actions"', false)
            ->assertSee('class="home-pill-button"', false)
            ->assertSee('>$ FREE</p>', false)
            ->getContent();

        preg_match_all('/<article class="home-show-card">(.*?)<\/article>/s', $html, $matches);

        $this->assertCount(2, $matches[1]);

        foreach ($matches[1] as $showCard) {
            $detailsPosition = strpos($showCard, 'class="home-show-copy"');
            $countdownPosition = strpos($showCard, 'class="home-event-countdown"');
            $actionsPosition = strpos($showCard, 'class="home-show-actions"');

            $this->assertNotFalse($detailsPosition);
            $this->assertNotFalse($countdownPosition);
            $this->assertNotFalse($actionsPosition);
            $this->assertLessThan($countdownPosition, $detailsPosition);
            $this->assertLessThan($actionsPosition, $countdownPosition);
            $this->assertSame(4, substr_count($showCard, 'data-countdown-unit='));
        }
    }

    public function test_shows_page_includes_additional_published_future_events_and_hides_past_events(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'Future CMS Show',
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => 'future-cms-show',
            'metadata' => [
                'starts_at' => '2026-09-30T20:00',
                'timezone' => 'America/Panama',
                'location' => 'Panama City',
                'ticketing_mode' => 'rsvp',
                'action_type' => 'rsvp',
                'price_cents' => 0,
            ],
        ]);

        EditorialContent::factory()->published()->create([
            'type' => ContentType::Event->value,
            'title' => 'Past CMS Show',
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => 'past-cms-show',
            'metadata' => [
                'starts_at' => '2026-08-01T20:00',
                'timezone' => 'America/Panama',
                'location' => 'Panama City',
                'ticketing_mode' => 'rsvp',
                'action_type' => 'rsvp',
                'price_cents' => 0,
            ],
        ]);

        $response = $this->get(route('shows'))
            ->assertOk()
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Future CMS Show')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertDontSee('Past CMS Show')
            ->assertSee('data-free-event-rsvp="future-cms-show"', false);

        $html = $response->getContent();

        $this->assertSame(3, substr_count($html, 'class="home-show-card"'));
        $this->assertLessThan(strpos($html, 'Future CMS Show'), strpos($html, 'Reny Renteria en Concierto'));
        $this->assertLessThan(strpos($html, 'Festival de la Rosa Dorada'), strpos($html, 'Future CMS Show'));
    }

    public function test_store_page_normalizes_legacy_royal_pass_cta_label(): void
    {
        SitePageSetting::create([
            'page' => StorefrontSettingsService::PAGE,
            'section' => StorefrontSettingsService::SECTION,
            'status' => SitePageSetting::STATUS_PUBLISHED,
            'payload' => [
                'royal_pass' => [
                    'cta_label' => 'Buy here',
                ],
            ],
            'published_at' => now(),
        ]);

        $this->get('/store')
            ->assertOk()
            ->assertSee('Unlock Royal Pass')
            ->assertDontSee('Buy here');
    }

    public function test_checkout_screen_renders_product_details_and_inline_paypal_flow(): void
    {
        $response = $this->get(route('store.checkout', ['product' => 'listening']));

        $response
            ->assertOk()
            ->assertSee('class="golden-stage-page checkout-page checkout-dedicated-page"', false)
            ->assertSee('class="store-shell home-shell golden-stage-shell checkout-stage-shell"', false)
            ->assertSee(asset('images/reny-renteria-logo-white.png'), false)
            ->assertSee('class="stage-lights"', false)
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Rock &amp; Folk Pty, Ciudad de Panama', false)
            ->assertSee('Dec 16, 2026 - 7:30 PM')
            ->assertSee('$15')
            ->assertSee('images/store/rosa-dorada.png')
            ->assertSee('data-dedicated-checkout', false)
            ->assertSee('data-checkout-product="listening"', false)
            ->assertSee('data-product-image="'.asset('images/store/rosa-dorada.png').'"', false)
            ->assertSee('data-product-price-value="15.00"', false)
            ->assertSee('data-copy-url="'.route('store.checkout', ['product' => 'listening']).'"', false)
            ->assertDontSee('data-auto-open-checkout', false)
            ->assertDontSee('id="bagLayer"', false)
            ->assertSee('id="paypalButtons"', false)
            ->assertSee('Your payment details are handled securely by PayPal.')
            ->assertSee('id="nameField"', false)
            ->assertSee('id="emailField"', false)
            ->assertDontSee('id="phoneField"', false)
            ->assertSee('id="countryField"', false)
            ->assertDontSee('pattern="^\+[1-9][0-9]{6,14}$"', false)
            ->assertSee('Select country')
            ->assertDontSee('data-payment-method="paypal"', false)
            ->assertSee('data-create-order-endpoint="'.route('checkout.paypal.orders').'"', false)
            ->assertSee('id="purchaseConfirmationPanel"', false)
            ->assertSee('data-checkout-payment-panel', false)
            ->assertSee('id="nameFieldError"', false)
            ->assertDontSee('Load PayPal checkout');
    }

    public function test_deluxe_checkout_is_available_when_the_catalog_product_is_valid(): void
    {
        $this->get(route('store.checkout', ['product' => 'deluxe']))
            ->assertOk()
            ->assertSee('Work in Progress')
            ->assertSee('$24');
    }

    public function test_royal_pass_checkout_uses_membership_card_art(): void
    {
        $royalPassImage = asset('images/store/royal-pass.png');

        $this->get(route('store.checkout', ['product' => 'royal']))
            ->assertOk()
            ->assertSee('Royal Pass')
            ->assertSee('src="'.$royalPassImage.'"', false)
            ->assertSee('alt="Royal Pass membership card"', false)
            ->assertSee('data-product-image="'.$royalPassImage.'"', false)
            ->assertDontSee('free trial', false)
            ->assertDontSee('trial period', false);
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
            ->assertSee('data-product-image="'.$assetUrl.'"', false);

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
        $this->assertStringContainsString('const initializeDedicatedCheckout = () => {', $js);
        $this->assertStringContainsString("source: 'dedicated_checkout_url'", $js);
        $this->assertStringContainsString('if (!initializeDedicatedCheckout()) {', $js);
        $this->assertStringContainsString('purchaseConfirmationPanel.hidden = false;', $js);
        $this->assertStringContainsString('initializeVisiblePayPalCheckout();', $js);
        $this->assertStringContainsString("image.className = 'store-bag-image';", $js);
        $this->assertStringContainsString('customer_name', $js);
        $this->assertStringContainsString('customer_country', $js);
        $this->assertStringNotContainsString('customer_phone', $js);
        $this->assertStringNotContainsString('Add a valid international phone number.', $js);
        $this->assertStringNotContainsString('completePurchaseButton', $js);
        $this->assertStringNotContainsString('Load PayPal checkout', $js);
        $this->assertStringContainsString('input, select, textarea, iframe, [tabindex]', $js);
    }

    public function test_store_checkout_rehydrates_after_persistent_public_navigation(): void
    {
        $js = $this->frontendJavaScriptSource();

        $this->assertStringContainsString('const initializeStoreInteractions = (root = document) => {', $js);
        $this->assertStringContainsString('initializeStoreInteractions(root);', $js);
        $this->assertStringContainsString('scope.querySelector?.(\'[data-buy]\')', $js);
        $this->assertStringContainsString('document.getElementById(\'openBag\')?.addEventListener', $js);
        $this->assertStringContainsString('document.querySelectorAll(\'[data-close]\')', $js);
        $this->assertStringContainsString('paypalButtons.hidden = !hasItems;', $js);
        $this->assertStringNotContainsString('paymentButtons.forEach((button) =>', $js);
        $this->assertStringContainsString('window.renyStoreKeydownAbort?.abort();', $js);
    }

    public function test_checkout_screen_rejects_unknown_product(): void
    {
        $this->get('/store/checkout/not-real')
            ->assertNotFound();
    }

    public function test_shows_page_uses_home_card_ratio_rules(): void
    {
        $css = $this->frontendCssSource();

        $this->assertStringContainsString('.home-shell .home-show-card', $css);
        $this->assertStringContainsString('.home-shell .home-show-image', $css);
        $this->assertStringContainsString('aspect-ratio: 1;', $css);
        $this->assertStringContainsString('object-fit: cover;', $css);
        $this->assertStringNotContainsString('storefront-card.is-event-secondary .storefront-image', $css);
    }

    public function test_store_intro_uses_accessible_text_color(): void
    {
        $css = $this->frontendCssSource();

        $this->assertMatchesRegularExpression(
            '/\.store-content \.public-page-intro\s*\{[^}]*color:\s*#ffffff;/s',
            $css,
        );
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
