<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
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

    public function test_home_music_buttons_have_real_play_and_checkout_actions(): void
    {
        $album = $this->publishedMusic(ContentType::MusicalAlbum, 'Launch Album', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                ['track_name' => 'Intro'],
                ['track_name' => 'Single'],
            ],
        ]);
        $this->publishedMusic(ContentType::MusicalAlbum, 'Launch Album Two', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                ['track_name' => 'Intro'],
                ['track_name' => 'Finale'],
            ],
        ]);

        $single = $this->publishedMusic(ContentType::Song, 'Launch Single', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'audio_url' => 'https://audio.test/launch-single.mp3',
        ]);

        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('data-music-play', false)
            ->assertSee(route('music.play', $album), false)
            ->assertSee(route('music.play', $single), false)
            ->assertSee('data-buy="deluxe"', false)
            ->assertSee('id="paypalButtons"', false)
            ->assertDontSee('Load PayPal checkout')
            ->assertSee('Buy Deluxe')
            ->assertSee('Launch Album')
            ->assertSee('Launch Single');

        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'class="home-buy-deluxe"'));
        $this->assertStringNotContainsString('href="'.route('music.albums').'"', $html);
        $this->assertStringNotContainsString('href="'.route('music.singles').'"', $html);
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
            ->assertSee('href="'.route('music.singles').'"', false)
            ->assertSee('href="'.route('music.playlists').'"', false)
            ->assertSee('id="musicPlayerPrevious"', false)
            ->assertSee('id="musicPlayerNext"', false)
            ->assertSee('id="musicPlayerShuffle"', false)
            ->assertSee('id="musicPlayerRepeat"', false)
            ->assertSee('data-music-player-close', false)
            ->assertDontSee('id="musicPlayerDetail"', false);

        foreach (['/videos', '/photos', '/community', '/store'] as $path) {
            $this->get($path)
                ->assertOk()
                ->assertSee('href="'.route('music').'"', false)
                ->assertSee('data-public-page-root', false)
                ->assertSee('id="musicPlayerLayer"', false);
        }
    }

    public function test_locked_music_cards_do_not_render_audio_urls_before_access_check(): void
    {
        $this->publishedMusic(ContentType::Song, 'Royal Preview Single', [
            'visibility' => VisibilityAudience::Royal->value,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'audio_url' => 'https://audio.test/royal-preview.mp3',
        ]);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Royal Preview Single')
            ->assertSee('Login required')
            ->assertDontSee('https://audio.test/royal-preview.mp3');
    }

    public function test_music_view_all_pages_and_empty_state_render(): void
    {
        $deluxeUrl = route('store.checkout', ['product' => 'deluxe']);
        $this->publishedMusic(ContentType::MusicalAlbum, 'Full Album One', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                ['track_name' => 'Intro'],
                ['track_name' => 'Finale'],
            ],
        ]);

        $response = $this->get('/music/albums');

        $response
            ->assertOk()
            ->assertSee('Full Album One')
            ->assertSee('data-buy="deluxe"', false)
            ->assertSee('data-buy-url="'.$deluxeUrl.'"', false)
            ->assertSee('id="bagLayer"', false)
            ->assertSee('name="csrf-token"', false)
            ->assertSee('id="paypalButtons"', false)
            ->assertSee('Buy Deluxe')
            ->assertSee('data-analytics-screen="music_albums"', false)
            ->assertDontSee('href="'.$deluxeUrl.'"', false);

        $this->assertSame(1, substr_count($response->getContent(), 'class="album-deluxe-button"'));

        $this->get('/music/singles')
            ->assertOk()
            ->assertSee('No published singles yet.');

        $this->get('/music/playlists')
            ->assertOk()
            ->assertSee('No published playlists yet.');
    }

    public function test_music_route_renders_playlists_from_existing_tracks(): void
    {
        $single = $this->publishedMusic(ContentType::Song, 'Playlist Single', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);
        $album = $this->publishedMusic(ContentType::MusicalAlbum, 'Playlist Album', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                ['track_name' => 'Album Track'],
            ],
        ]);

        $playlist = $this->publishedMusic(ContentType::MusicPlaylist, 'CMS Playlist', [
            'tracks' => [
                'song:'.$single->id,
                'album:'.$album->id.':0',
            ],
        ]);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Playlists')
            ->assertSee('CMS Playlist')
            ->assertSee(route('music.play', $single), false)
            ->assertSee(route('music.play', $album), false)
            ->assertSee(route('music.play', $playlist), false)
            ->assertSee('Playlist Single')
            ->assertSee('Playlist Album - Album Track');

        $this->get(route('music.playlists'))
            ->assertOk()
            ->assertSee('CMS Playlist');
    }

    public function test_playlist_playback_endpoint_returns_playable_track_queue(): void
    {
        $singleAudio = $this->mediaAsset(MediaAssetType::Audio->value);
        $albumAudio = $this->mediaAsset(MediaAssetType::Audio->value);

        $single = $this->publishedMusic(ContentType::Song, 'Playable Playlist Single', [
            'audio_asset_id' => $singleAudio->id,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);
        $single->mediaAssets()->attach($singleAudio->id, ['role' => 'audio', 'sort_order' => 0]);

        $album = $this->publishedMusic(ContentType::MusicalAlbum, 'Playable Playlist Album', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                ['track_name' => 'Queued Track', 'track_audio_asset_id' => $albumAudio->id],
            ],
        ]);
        $album->mediaAssets()->attach($albumAudio->id, ['role' => 'track_audio', 'sort_order' => 0]);

        $playlist = $this->publishedMusic(ContentType::MusicPlaylist, 'Playable CMS Playlist', [
            'tracks' => [
                'song:'.$single->id,
                'album:'.$album->id.':0',
            ],
        ]);

        $this->getJson(route('music.play', $playlist))
            ->assertOk()
            ->assertJsonPath('state', 'ready')
            ->assertJsonPath('audio_url', $singleAudio->publicUrl())
            ->assertJsonCount(2, 'queue')
            ->assertJsonPath('queue.0.title', 'Playable Playlist Single')
            ->assertJsonPath('queue.1.title', 'Playable Playlist Album - Queued Track')
            ->assertJsonPath('queue.1.audio_url', $albumAudio->publicUrl());
    }

    public function test_music_route_lists_cms_single_even_when_release_dates_are_future(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $audio = $this->mediaAsset(MediaAssetType::Audio->value);
        $artwork = $this->mediaAsset(MediaAssetType::Thumbnail->value);

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            'action' => 'publish',
            'type' => ContentType::Song->value,
            'title' => 'Future CMS Single',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'audio_asset_id' => $audio->id,
                'artwork_asset_id' => $artwork->id,
                'release_date_member_view' => '2030-07-01T10:00',
                'release_date_open_view' => '2030-07-02T10:00',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('scheduled_at', null);

        $this->get(route('music'))
            ->assertOk()
            ->assertSee('Future CMS Single');
    }

    public function test_music_playback_endpoint_returns_ready_error_and_access_states(): void
    {
        $open = $this->publishedMusic(ContentType::Song, 'Open Audio', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'audio_url' => 'https://audio.test/open.mp3',
            'artwork_asset_id' => $this->mediaAsset(MediaAssetType::Thumbnail->value)->id,
        ]);
        $open->mediaAssets()->attach(data_get($open->metadata, 'artwork_asset_id'), ['role' => 'artwork', 'sort_order' => 0]);

        $missingAudio = $this->publishedMusic(ContentType::Song, 'Missing Audio', [
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);

        $member = $this->publishedMusic(ContentType::Song, 'Member Audio', [
            'visibility' => VisibilityAudience::Member->value,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'audio_url' => 'https://audio.test/member.mp3',
        ]);

        $royal = $this->publishedMusic(ContentType::Song, 'Royal Audio', [
            'visibility' => VisibilityAudience::Royal->value,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
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
            ->assertJsonPath('audio_url', 'https://audio.test/open.mp3')
            ->assertJsonPath('image_url', $open->mediaAssets->first()->publicUrl());

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

    private function mediaAsset(string $type): MediaAsset
    {
        $isAudio = $type === MediaAssetType::Audio->value;

        return MediaAsset::create([
            'type' => $type,
            'title' => "Reusable {$type}",
            'disk' => 'public',
            'path' => $isAudio ? "media/{$type}/asset.mp3" : "media/{$type}/asset.jpg",
            'original_filename' => $isAudio ? "asset-{$type}.mp3" : "asset-{$type}.jpg",
            'mime_type' => $isAudio ? 'audio/mpeg' : 'image/jpeg',
            'extension' => $isAudio ? 'mp3' : 'jpg',
            'size_bytes' => 1024,
            'processing_status' => MediaProcessingStatus::Ready->value,
            'is_public' => true,
            'alt_text' => $isAudio ? null : 'Reusable asset alt text',
        ]);
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
