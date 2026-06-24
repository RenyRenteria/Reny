<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\VisibilityAudience;
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

    public function test_video_card_escapes_cms_title_and_renders_accents(): void
    {
        $this->publishedVideo(
            'Canción <script>alert("xss-156")</script> Ñoño',
            'Estreno en Tu Mañana <b>en vivo</b>',
            'music-video',
        );

        $response = $this->get('/videos');

        $response->assertOk();
        // Accents survive as real UTF-8 text in the rendered card.
        $response->assertSee('Canción', false);
        $response->assertSee('Tu Mañana', false);
        // CMS-controlled markup is escaped, not executed.
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert("xss-156")', false);
        $response->assertDontSee('<b>en vivo</b>', false);
        // Layout is preserved.
        $response->assertSee('class="video-card"', false);
        $response->assertSee('class="video-load-button"', false);

        // Attribute pipeline (data-* + aria-label) feeds GA item_name and a11y.
        // strip_tags drops the markup, e() escapes the surviving text; quotes
        // become &quot; so the payload can never break out of the attribute.
        $safeTitle = 'Canción alert(&quot;xss-156&quot;) Ñoño';
        $response->assertSee('data-youtube-title="Reny Renteria - '.$safeTitle.'"', false);
        $response->assertSee('data-analytics-label="'.$safeTitle.'"', false);
        $response->assertSee('aria-label="Play Reny Renteria - '.$safeTitle.'"', false);
        // No raw quote breakout inside any attribute value.
        $response->assertDontSee('alert("xss-156")', false);
    }

    public function test_playlist_card_escapes_cms_title_and_renders_accents(): void
    {
        // No resolvable youtube id => unavailable playlist => the button branch
        // renders both data-youtube-title and data-analytics-label.
        $this->publishedVideo(
            'Sesión <script>alert("xss-playlist")</script> Música',
            'Conversaciones con acentós y <i>énfasis</i>',
            'series',
            youtubeUrl: 'https://example.com/not-a-video',
        );

        $response = $this->get('/videos');

        $response->assertOk();
        $response->assertSee('Sesión', false);
        $response->assertSee('acentós', false);
        $response->assertSee('&lt;script&gt;', false);
        $response->assertDontSee('<script>alert("xss-playlist")', false);
        $response->assertDontSee('<i>énfasis</i>', false);
        $response->assertSee('class="playlist-card"', false);

        $safeTitle = 'Sesión alert(&quot;xss-playlist&quot;) Música';
        $response->assertSee('data-youtube-title="'.$safeTitle.'"', false);
        $response->assertSee('data-analytics-label="'.$safeTitle.'"', false);
        $response->assertDontSee('alert("xss-playlist")', false);
    }

    public function test_card_body_and_attributes_agree_on_html_entities(): void
    {
        // Regression guard for the removed html_entity_decode: a title carrying
        // an HTML entity must serialize identically in the {{ }} body and in the
        // data-analytics-label attribute. With the old decode the attribute would
        // have collapsed "&amp;amp;" back to "&amp;", silently skewing GA item_name.
        $this->publishedVideo('Sol &amp; Luna', 'En vivo', 'music-video');

        $response = $this->get('/videos');

        $response->assertOk();
        // Body: {{ }} double-encodes the literal entity.
        $response->assertSee('<h4>Sol &amp;amp; Luna</h4>', false);
        // Attribute: same double-encoded string, not the decoded "Sol &amp; Luna".
        $response->assertSee('data-analytics-label="Sol &amp;amp; Luna"', false);
        $response->assertDontSee('data-analytics-label="Sol &amp; Luna"', false);
    }

    private function publishedVideo(
        string $title,
        string $summary,
        string $category,
        string $youtubeUrl = 'https://www.youtube.com/watch?v=abc12345678',
    ): EditorialContent {
        return EditorialContent::factory()->published()->create([
            'type' => ContentType::Video->value,
            'visibility' => VisibilityAudience::Open->value,
            'title' => $title,
            'summary' => $summary,
            'body' => null,
            'metadata' => [
                'youtube_url' => $youtubeUrl,
                'category' => $category,
            ],
        ]);
    }
}
