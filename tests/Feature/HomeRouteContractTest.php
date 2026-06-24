<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeRouteContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_route_returns_public_landing_contract(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-analytics-screen="home"', false)
            ->assertSee('Reny Renteria', false)
            ->assertSee('Watch<br>Now', false)
            ->assertSee('Royal Pass')
            ->assertSee('Upcoming Shows')
            ->assertSee('Latest Singles')
            ->assertSee('data-buy="royal"', false);
    }
}
