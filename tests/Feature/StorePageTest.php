<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorePageTest extends TestCase
{
    public function test_store_page_mounts_royals_exclusives_and_event_posters(): void
    {
        $response = $this->get('/store');

        $response->assertOk();
        $response->assertSee('Store');
        $response->assertSee("Royal's Exclusives", false);
        $response->assertSee('Events');
        $response->assertSee('DM Sans');
        $response->assertSee('Checkout Drop');
        $response->assertSee('RSVP');
        $response->assertSee('Buy Ticket');
        $response->assertSee('<b>Date</b> Aug 24, 2026', false);
        $response->assertSee('<b>Place</b> Panama City, Panama', false);
        $response->assertSee('href="/store"', false);
        $response->assertSee('aria-current="page"', false);
        $response->assertDontSee('Objects');
        $response->assertDontSee('Crown Collection');
        $response->assertDontSee('Fast Checkout');

        $html = $response->getContent();

        $royalsExclusivesPosition = strpos($html, "<h2>Royal's Exclusives</h2>");
        $eventsPosition = strpos($html, '<h2>Events</h2>');

        $this->assertNotFalse($royalsExclusivesPosition);
        $this->assertNotFalse($eventsPosition);
        $this->assertLessThan($eventsPosition, $royalsExclusivesPosition);
        $this->assertSame(6, substr_count($html, 'class="product-card"'));
        $this->assertSame(3, substr_count($html, 'class="event-card'));
        preg_match_all('/<article class="event-card[^"]*">.*?<img /s', $html, $eventPosterImages);
        $this->assertSame(3, count($eventPosterImages[0]));
        $this->assertStringNotContainsString('<iframe', $html);
        $this->assertStringNotContainsString('<video', $html);
        $this->assertStringNotContainsString('youtube', strtolower($html));
    }

    public function test_existing_tabs_link_to_store_route(): void
    {
        foreach (['/', '/videos', '/photos'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="' . url('/store') . '"', false)
                ->assertDontSee('href="#store"', false);
        }
    }
}
