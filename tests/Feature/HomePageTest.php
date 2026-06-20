<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class HomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_home_page_composes_cms_video_store_and_music_sources(): void
    {
        $this->publishedContent(ContentType::Video, [
            'title' => 'CMS Home Video',
            'summary' => 'Featured from Videos CMS',
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
            ],
        ]);

        $this->publishedContent(ContentType::DeluxeAlbum, [
            'title' => 'CMS Deluxe Album',
            'metadata' => [
                'track_count' => '27',
            ],
        ]);

        $this->publishedContent(ContentType::Song, [
            'title' => 'CMS Lead Single',
            'metadata' => [
                'artist' => 'Reny CMS',
            ],
        ]);

        $response = $this->get('/')
            ->assertOk()
            ->assertSee('data-analytics-screen="home"', false)
            ->assertSee('CMS Home Video')
            ->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('CMS Deluxe Album')
            ->assertSee('CMS Lead Single')
            ->assertSee('data-buy="royal"', false)
            ->assertSee('data-buy="deluxe"', false)
            ->assertSee('data-rsvp="concert"', false);

        $html = $response->getContent();

        $this->assertStringNotContainsString('class="tab is-active"', $html);
        $this->assertStringNotContainsString('aria-current="page"', $html);
        $this->assertStringContainsString('class="home-show-card"', $html);
        $this->assertStringContainsString('class="home-royal-pass"', $html);
    }

    public function test_home_hides_royal_pass_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/')
            ->assertOk()
            ->assertDontSee('class="home-royal-pass"', false)
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertSee('Watch more');
    }

    public function test_home_falls_back_to_storefront_events_when_payload_events_are_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->view('home', [
            'publicCms' => [
                'events' => [],
            ],
            'rsvpTickets' => [],
        ]);

        $response
            ->assertSee('Upcoming Shows')
            ->assertSee('Reny Renteria en Concierto')
            ->assertSee('Festival de la Rosa Dorada')
            ->assertDontSee('class="home-royal-pass"', false);
    }

    public function test_home_payload_is_available_from_public_cms_endpoint(): void
    {
        $this->publishedContent(ContentType::Song, [
            'title' => 'Payload Single',
        ]);

        $this->getJson(route('public-content.payload', 'home'))
            ->assertOk()
            ->assertJsonPath('_cms_source', 'cms')
            ->assertJsonPath('singles.0.title', 'Payload Single')
            ->assertJsonPath('events.0.title', 'Reny Renteria en Concierto')
            ->assertJsonPath('royal_pass.product_key', 'royal');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function publishedContent(ContentType $type, array $overrides = []): EditorialContent
    {
        return EditorialContent::factory()->create([
            'type' => $type,
            'status' => EditorialStatus::Published,
            'published_at' => now(),
            ...$overrides,
        ]);
    }
}
