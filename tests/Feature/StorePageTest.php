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
        $response->assertSee('images/store/reny-concert.png');
        $response->assertSee('images/store/rosa-dorada.png');
        $response->assertSee('images/store/work-in-progress.png');
        $response->assertSee('images/store/crown-collection.png');
        $response->assertSee('Selected currency is a reference. PayPal checkout is charged in USD.');
        $response->assertSee('data-rsvp="concert"', false);
        $response->assertSee('data-buy="listening"', false);
        $response->assertSee('data-buy="deluxe"', false);
        $response->assertSee('data-buy="merch"', false);
        $response->assertDontSee('data-buy="concert"', false);
        $response->assertSee('class="tab is-active"', false);
        $response->assertSee('href="'.url('/store').'"', false);
        $response->assertSee('data-payment-method="paypal"', false);
        $response->assertSee('role="radio"', false);
        $response->assertSee('data-rsvp-endpoint="'.route('store.rsvp').'"', false);
        $response->assertSee('Free RSVP confirms a reservation on this account.');

        $html = $response->getContent();

        $this->assertSame(4, substr_count($html, 'storefront-card'));
        $this->assertLessThan(strpos($html, 'Reny Renteria en Concierto'), strpos($html, 'Royal Pass'));
        $this->assertLessThan(strpos($html, 'Festival de la Rosa Dorada'), strpos($html, 'Reny Renteria en Concierto'));
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
