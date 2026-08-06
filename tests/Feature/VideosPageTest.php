<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Models\EditorialContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VideosPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_videos_page_uses_click_to_load_cards(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Video->value,
            'title' => 'CMS Video Card',
            'summary' => 'CMS featured video',
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
            ],
        ]);

        $response = $this->get('/videos');

        $response->assertOk();
        $response->assertSee('Music videos');
        $response->assertSee('Series (Playlists)');
        $response->assertSee('Performances videos');
        $response->assertSee('Behind the scenes');
        $response->assertSee('Vlogs');
        $response->assertSee('class="video-load-button"', false);
        $response->assertSee('id="videoPlayerLayer"', false);
        $response->assertSee('data-video-player', false);
        $response->assertSee('class="video-card-external"', false);
        $response->assertSee('href="'.route('videos', ['category' => 'music_videos']).'"', false);
        $response->assertSee('href="'.route('videos', ['category' => 'series']).'"', false);
        $response->assertSee('href="'.route('videos', ['category' => 'performances']).'"', false);
        $response->assertSee('href="'.route('videos', ['category' => 'behind_the_scenes']).'"', false);
        $response->assertSee('href="'.route('videos', ['category' => 'vlogs']).'"', false);
        $response->assertSee('https://www.youtube.com/embed/dQw4w9WgXcQ', false);
        $response->assertSee('https://www.youtube.com/watch?v=dQw4w9WgXcQ', false);
        $response->assertSee('CMS Video Card');
        $response->assertDontSee('I Swear');
        $response->assertSee('class="videos-shell home-shell videos-stage-shell"', false);
        $response->assertSee('images/reny-renteria-logo-white.png', false);
        $response->assertSee('class="stage-lights" aria-hidden="true"', false);
        $response->assertSee('class="stage-light stage-light--one"', false);
        $response->assertSee('class="stage-light stage-light--two"', false);
        $response->assertSee('class="stage-light stage-light--three"', false);
        $response->assertDontSee('class="home-royal-pass-images"', false);
        $response->assertSee('family=DM+Sans', false);

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(1, substr_count($html, 'class="video-load-button"'));
    }

    public function test_videos_page_preserves_automatic_catalog_when_cms_is_empty(): void
    {
        $response = $this->get('/videos');

        $response->assertOk();
        $response->assertSee('Reny Renteria - Take a bite (Official Music Video)');
        $response->assertSee('I Swear');
        $response->assertSee('Raspao a Dolar');
        $response->assertSee('Places');
        $response->assertSee('Wave');
        $response->assertSee('Visitando Mas23');
        $response->assertSee('class="videos-shell home-shell videos-stage-shell"', false);

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(19, substr_count($html, 'class="video-load-button"'));
        $this->assertSame(2, substr_count($html, 'class="playlist-card"'));
        $this->assertSame(0, substr_count($html, 'class="video-empty-state"'));
    }

    public function test_videos_category_filter_renders_static_listing_when_cms_is_empty(): void
    {
        $response = $this->get(route('videos', ['category' => 'vlogs']));

        $response->assertOk();
        $response->assertSee('All videos');
        $response->assertSee('Vlogs');
        $response->assertSee('3 videos');
        $response->assertSee('Visitando Mas23');
        $response->assertDontSee('No vlogs published yet.');
        $response->assertDontSee('Performances videos');
        $response->assertDontSee('Behind the scenes');
    }
}
