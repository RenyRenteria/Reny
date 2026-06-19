<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class VideosPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_videos_page_uses_click_to_load_cards(): void
    {
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

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, '<iframe'));
        $this->assertSame(19, substr_count($html, 'class="video-load-button"'));
    }

    public function test_videos_category_filter_renders_single_listing(): void
    {
        $response = $this->get(route('videos', ['category' => 'vlogs']));

        $response->assertOk();
        $response->assertSee('All videos');
        $response->assertSee('Vlogs');
        $response->assertSee('3 videos');
        $response->assertSee('Visitando Mas23');
        $response->assertDontSee('Performances videos');
        $response->assertDontSee('Behind the scenes');
    }
}
