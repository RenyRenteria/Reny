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

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(1, substr_count($html, 'class="video-load-button"'));
    }

    public function test_videos_category_filter_renders_single_listing(): void
    {
        $response = $this->get(route('videos', ['category' => 'vlogs']));

        $response->assertOk();
        $response->assertSee('All videos');
        $response->assertSee('Vlogs');
        $response->assertSee('0 videos');
        $response->assertSee('No vlogs published yet.');
        $response->assertDontSee('Visitando Mas23');
        $response->assertDontSee('Performances videos');
        $response->assertDontSee('Behind the scenes');
    }
}
