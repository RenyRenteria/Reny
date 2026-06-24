<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicHomeContractTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_public_home_renders_stable_layout_and_purchase_contract(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('data-analytics-screen="home"', false)
            ->assertSee('data-public-page-root', false)
            ->assertSee('class="store-shell home-shell"', false)
            ->assertSee('class="home-royal-pass"', false)
            ->assertSee('class="video-hero home-video-hero"', false)
            ->assertSee('data-buy="royal"', false)
            ->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'royal']).'"', false)
            ->assertSee('href="'.route('videos').'"', false);
    }
}
