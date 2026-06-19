<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_page_mounts_clean_shop_with_paypal_checkout(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('Official store');
        $response->assertSee('Reny Shop');
        $response->assertSee('Shop');
        $response->assertSee('$4.99/mo');
        $response->assertSee('Reference prices update here. Checkout is charged in USD.');
        $response->assertSee('Selected currency is a reference. PayPal checkout is charged in USD.');
        $response->assertSee('data-filter="membership"', false);
        $response->assertSee('data-filter="music"', false);
        $response->assertSee('data-filter="merch"', false);
        $response->assertSee('aria-pressed="true"', false);
        $response->assertSee('Buy album');
        $response->assertSee('Buy music');
        $response->assertSee('Join membership');
        $response->assertSee('Add to bag');
        $response->assertSee('Events');
        $response->assertSee('Reny Live - Studio Night');
        $response->assertSee('Aug 24, 2026');
        $response->assertSee('Panama City');
        $response->assertSee('Buy ticket');
        $response->assertSee('RSVP');
        $response->assertSee('reny-store-concert-poster.png');
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.url('/store').'"', false);
        $response->assertSee('data-payment-method="paypal"', false);
        $response->assertSee('role="radio"', false);
        $response->assertDontSee('data-payment-method="card"', false);
        $response->assertDontSee('data-payment-method="apple_pay"', false);
        $response->assertDontSee('data-payment-method="local"', false);
        $response->assertSee('data-rsvp="making"', false);
        $response->assertSee('data-rsvp-endpoint="'.route('store.rsvp').'"', false);
        $response->assertSee('Free RSVP confirms a reservation on this account.');
        $response->assertDontSee('fan@renyrenteria.com', false);
        $response->assertDontSee('id="localReferenceField"', false);
        $response->assertDontSee('id="localReceiptField"', false);

        $html = $response->getContent();

        $this->assertSame(5, substr_count($html, 'class="store-product-card"'));
        $this->assertSame(3, substr_count($html, 'class="store-event-card"'));
        $this->assertLessThan(strpos($html, 'Events'), strpos($html, 'Reny Shop'));
        $this->assertLessThan(strpos($html, 'Events'), strpos($html, 'id="market-title"'));
        $this->assertStringContainsString('data-category="membership"', $html);
        $this->assertStringContainsString('data-category="music"', $html);
        $this->assertStringContainsString('data-category="merch"', $html);
        $this->assertStringNotContainsString('Upcoming concert', $html);
        $this->assertStringNotContainsString('role="tab"', $html);
        $this->assertStringNotContainsString('aria-selected', $html);
        $this->assertStringNotContainsString('Crown Jacket', $html);
        $this->assertStringNotContainsString('data-buy="crown"', $html);
        $this->assertStringNotContainsString('data-buy="making"', $html);
        $this->assertStringNotContainsString('Objects', $html);
        $this->assertStringNotContainsString('Crown Collection', $html);
        $this->assertStringNotContainsString('Fast Checkout', $html);
        $this->assertStringNotContainsString('LATAM', $html);
        $this->assertStringNotContainsString('GLOBAL', $html);
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
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
