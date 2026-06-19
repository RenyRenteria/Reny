<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorePageTest extends TestCase
{
    public function test_store_page_mounts_shop_with_events_and_royal_exclusives(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('Upcoming concert');
        $response->assertSee("Royal's Exclusives");
        $response->assertSee('$4.99/mo');
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
        $response->assertSee('data-payment-method="card"', false);
        $response->assertSee('data-payment-method="apple_pay"', false);
        $response->assertSee('data-payment-method="local"', false);
        $response->assertSee('data-unavailable-reason="card_provider_not_configured"', false);
        $response->assertSee('data-unavailable-reason="apple_pay_provider_not_configured"', false);
        $response->assertSee('data-unavailable-reason="local_provider_not_configured"', false);
        $response->assertSee('id="localReferenceField"', false);
        $response->assertSee('id="localReceiptField"', false);

        $html = $response->getContent();

        $this->assertSame(5, substr_count($html, 'class="store-product-card"'));
        $this->assertSame(3, substr_count($html, 'class="store-event-card"'));
        $this->assertLessThan(strpos($html, 'Events'), strpos($html, "Royal's Exclusives"));
        $this->assertStringNotContainsString('Crown Jacket', $html);
        $this->assertStringNotContainsString('data-buy="crown"', $html);
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
