<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RoyalPassAccessMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_shared_royal_pass_banner_follows_the_three_account_states_on_every_primary_tab(): void
    {
        $paths = ['/', '/community', '/videos', '/music', '/shows', '/store'];

        foreach ($paths as $path) {
            $response = $this->get($path)->assertOk();

            $response
                ->assertSee('data-royal-pass-banner', false)
                ->assertSee('aria-label="Get your Royal Pass to unlock access to our community."', false)
                ->assertSee('data-buy-url="'.route('store.checkout', ['product' => 'royal']).'"', false);
            $this->assertSame(1, substr_count($response->getContent(), 'data-royal-pass-banner'));
        }

        $freeAccount = User::factory()->create();

        foreach ($paths as $path) {
            $this->actingAs($freeAccount)
                ->get($path)
                ->assertOk()
                ->assertSee('data-royal-pass-banner', false);
        }

        $royalAccount = User::factory()->royal()->create();

        foreach ($paths as $path) {
            $this->actingAs($royalAccount)
                ->get($path)
                ->assertOk()
                ->assertDontSee('data-royal-pass-banner', false);
        }
    }

    public function test_free_accounts_can_read_community_content_but_receive_royal_upsells_for_writes(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Reny community update',
            'body' => '<p>The complete community post stays readable.</p>',
            'metadata' => ['comments_enabled' => true],
        ]);

        $freeAccount = User::factory()->create();

        $this->actingAs($freeAccount)
            ->get('/community')
            ->assertOk()
            ->assertSee('The complete community post stays readable.')
            ->assertSee('Royal Pass requerido')
            ->assertSee('Obtén Royal Pass para comentar')
            ->assertSee('aria-label="Obtén Royal Pass para reaccionar"', false)
            ->assertSee('href="'.route('store').'"', false)
            ->assertDontSee('data-community-like', false)
            ->assertDontSee('data-community-reply-form', false)
            ->assertDontSee('data-community-live-chat-form', false);

        $this->actingAs(User::factory()->royal()->create())
            ->get('/community')
            ->assertOk()
            ->assertSee('data-community-like', false)
            ->assertSee('data-community-reply-form', false)
            ->assertSee('data-community-live-chat-form', false);
    }

    public function test_royals_uses_the_home_royal_pass_banner_without_photos(): void
    {
        $this->get('/royals')
            ->assertOk()
            ->assertSee('class="home-royal-pass is-selected"', false)
            ->assertSee('class="home-royal-pass-selector"', false)
            ->assertSee('class="store-button home-unlock-button"', false)
            ->assertDontSee('class="home-royal-pass-images"', false)
            ->assertDontSee('images/photos/capri.jpg', false)
            ->assertDontSee('images/photos/performance.jpg', false)
            ->assertDontSee('images/photos/tvVisit.jpg', false);

        $this->get('/')
            ->assertOk()
            ->assertSee('class="home-royal-pass-images"', false);
    }
}
