<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomePageContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_home_page_exposes_expected_guest_content_contract(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-analytics-screen="home"', false)
            ->assertSee('Reny Renteria')
            ->assertSee('Upcoming Shows')
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Latest Singles')
            ->assertSee('data-buy="royal"', false)
            ->assertSee('data-free-event-rsvp="concert"', false);
    }
}
