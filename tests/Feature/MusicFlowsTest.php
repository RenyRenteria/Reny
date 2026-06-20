<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;
use App\Models\UserUnlock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MusicFlowsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('public_cms.cache_store', 'array');
        Cache::store('array')->flush();
    }

    public function test_home_music_buttons_have_real_view_all_and_play_actions(): void
    {
        $deluxeUrl = route('store', ['buy' => 'deluxe']);
        $album = $this->publishedMusic(ContentType::MusicalAlbum, 'Launch Album', [
            'tracklist' => "Intro\nSingle",
            'narrative' => 'Album narrative',
            'release_cycle' => 'Launch',
        ]);
        $this->publishedMusic(ContentType::MusicalAlbum, 'Launch Album Two', [
            'tracklist' => "Intro\nFinale",
            'narrative' => 'Second album narrative',
            'release_cycle' => 'Launch',
        ]);

        $single = $this->publishedMusic(ContentType::Song, 'Launch Single', [
            'artist' => 'Reny CMS',
            'duration_seconds' => 210,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Open->value,
            'audio_url' => 'https://audio.test/launch-single.mp3',
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('href="'.route('music.albums').'"', false)
            ->assertSee('href="'.route('music.singles').'"', false)
            ->assertSee('data-music-play', false)
            ->assertSee(route('music.play', $album), false)
            ->assertSee(route('music.play', $single), false)
            ->assertSee('href="'.$deluxeUrl.'"', false)
            ->assertSee('Buy Deluxe')
            ->assertSee('Launch Album')
            ->assertSee('Launch Single');

        $this->assertSame(2, substr_count($response->getContent(), 'class="album-deluxe-button"'));
    }

    public function test_music_route_renders_banner_and_public_nav_targets_music(): void
    {
        $this->get(route('music'))
            ->assertOk()
            ->assertSee('data-analytics-screen="music"', false)
            ->assertSee('Biggest')
            ->assertSee('Comeback Album!')
            ->assertSee('href="'.route('music').'"', false)
            ->assertSee('href="'.route('music.albums').'"', false)
            ->assertSee('href="'.route('music.singles').'"', false);

        foreach (['/videos', '/photos', '/community', '/store'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('music').'"', false);
        }
    }

    public function test_locked_music_cards_do_not_render_audio_urls_before_access_check(): void
    {
        $this->publishedMusic(ContentType::Song, 'Royal Preview Single', [
            'visibility' => VisibilityAudience::Royal->value,
            'duration_seconds' => 180,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Royal->value,
            'audio_url' => 'https://audio.test/royal-preview.mp3',
        ]);

        $this->get('/')
            ->assertOk()
            ->assertSee('Royal Preview Single')
            ->assertSee('Login required')
            ->assertDontSee('https://audio.test/royal-preview.mp3');
    }

    public function test_music_view_all_pages_and_empty_state_render(): void
    {
        $deluxeUrl = route('store', ['buy' => 'deluxe']);
        $this->publishedMusic(ContentType::MusicalAlbum, 'Full Album One', [
            'tracklist' => "Intro\nFinale",
            'narrative' => 'Album narrative',
            'release_cycle' => 'Launch',
        ]);

        $response = $this->get('/music/albums');

        $response
            ->assertOk()
            ->assertSee('Full Album One')
            ->assertSee('href="'.$deluxeUrl.'"', false)
            ->assertSee('Buy Deluxe')
            ->assertSee('data-analytics-screen="music_albums"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'class="album-deluxe-button"'));

        $this->get('/music/singles')
            ->assertOk()
            ->assertSee('No published singles yet.');
    }

    public function test_music_playback_endpoint_returns_ready_error_and_access_states(): void
    {
        $open = $this->publishedMusic(ContentType::Song, 'Open Audio', [
            'duration_seconds' => 180,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Open->value,
            'audio_url' => 'https://audio.test/open.mp3',
        ]);

        $missingAudio = $this->publishedMusic(ContentType::Song, 'Missing Audio', [
            'duration_seconds' => 180,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Open->value,
        ]);

        $member = $this->publishedMusic(ContentType::Song, 'Member Audio', [
            'visibility' => VisibilityAudience::Member->value,
            'duration_seconds' => 180,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Member->value,
            'audio_url' => 'https://audio.test/member.mp3',
        ]);

        $royal = $this->publishedMusic(ContentType::Song, 'Royal Audio', [
            'visibility' => VisibilityAudience::Royal->value,
            'duration_seconds' => 180,
            'release_date' => '2026-07-01',
            'credits' => 'Reny',
            'preview_visibility' => VisibilityAudience::Open->value,
            'full_visibility' => VisibilityAudience::Royal->value,
            'audio_url' => 'https://audio.test/royal.mp3',
        ]);

        $purchased = $this->publishedMusic(ContentType::Exclusive, 'Purchased Audio', [
            'visibility' => VisibilityAudience::Purchased->value,
            'purchase_key' => 'purchased-audio',
            'exclusive_kind' => 'audio',
            'access_note' => 'Purchased track',
            'audio_url' => 'https://audio.test/purchased.mp3',
        ]);

        $registered = User::factory()->create();
        $royalUser = User::factory()->royal()->create();

        $this->getJson(route('music.play', $open))
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('audio_url', 'https://audio.test/open.mp3');

        $this->getJson(route('music.play', $missingAudio))
            ->assertStatus(422)
            ->assertJsonPath('state', 'playback_error');

        $this->getJson(route('music.play', $member))
            ->assertStatus(401)
            ->assertJsonPath('state', 'login_required');

        $this->getJson(route('music.play', $royal))
            ->assertStatus(401)
            ->assertJsonPath('state', 'login_required');

        $this->actingAs($registered)
            ->getJson(route('music.play', $member))
            ->assertOk()
            ->assertJsonPath('state', 'ready');

        $this->actingAs($registered)
            ->getJson(route('music.play', $royal))
            ->assertForbidden()
            ->assertJsonPath('state', 'royal_required');

        $this->actingAs($royalUser)
            ->getJson(route('music.play', $royal))
            ->assertOk()
            ->assertJsonPath('audio_url', 'https://audio.test/royal.mp3');

        $this->actingAs($registered)
            ->getJson(route('music.play', $purchased))
            ->assertForbidden()
            ->assertJsonPath('state', 'content_locked');

        UserUnlock::create([
            'user_id' => $registered->id,
            'unlock_type' => 'content',
            'product_key' => 'purchased-audio',
            'title' => $purchased->title,
            'source_type' => 'editorial_content',
            'source_id' => (string) $purchased->id,
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        $this->actingAs($registered->fresh())
            ->getJson(route('music.play', $purchased))
            ->assertOk()
            ->assertJsonPath('audio_url', 'https://audio.test/purchased.mp3');
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function publishedMusic(ContentType $type, string $title, array $metadata = []): EditorialContent
    {
        $attributes = [
            'type' => $type->value,
            'title' => $title,
            'status' => EditorialStatus::Published->value,
            'visibility' => $metadata['visibility'] ?? VisibilityAudience::Open->value,
            'purchase_key' => $metadata['purchase_key'] ?? null,
            'published_at' => now(),
            'metadata' => array_diff_key($metadata, array_flip(['visibility', 'purchase_key'])),
        ];

        return EditorialContent::factory()->create($attributes);
    }
}
