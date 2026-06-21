<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_page_matches_four_slot_mockup_for_guest(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('Get your');
        $response->assertSee('Royal Pass');
        $response->assertSee('BUY HERE');
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
        $response->assertSee('data-rsvp="concert"', false);
        $response->assertSee('data-buy="listening"', false);
        $response->assertSee('data-buy="deluxe"', false);
        $response->assertSee('data-buy="merch"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'listening']).'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'deluxe']).'"', false);
        $response->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'merch']).'"', false);
        $response->assertDontSee('data-buy="concert"', false);
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.url('/store').'"', false);
        $response->assertSee('data-payment-method="paypal"', false);
        $response->assertSee('role="radio"', false);
        $response->assertSee('data-rsvp-endpoint="'.route('store.rsvp').'"', false);
        $response->assertSee('Free RSVP confirms a reservation on this account.');

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
            ->assertDontSee('BUY HERE')
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
            ->assertSee('data-buy="listening"', false)
            ->assertSee('data-buy-price-value="15.00"', false)
            ->assertSee('data-copy-url="'.route('store.checkout', ['product' => 'listening']).'"', false)
            ->assertSee('id="bagLayer"', false)
            ->assertSee('data-payment-method="paypal"', false)
            ->assertSee('data-create-order-endpoint="'.route('checkout.paypal.orders').'"', false)
            ->assertSee('GET TICKETS');
    }

    public function test_checkout_screen_rejects_unknown_product(): void
    {
        $this->get('/store/checkout/not-real')
            ->assertNotFound();
    }

    public function test_store_event_images_use_matching_ratio_rules(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

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
