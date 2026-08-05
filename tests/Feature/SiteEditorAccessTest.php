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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteEditorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_site_editor_requires_private_admin_session(): void
    {
        $this->get(route('admin.site-editor.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_fan_cannot_access_site_editor(): void
    {
        $fan = User::factory()->create(['role' => User::ROLE_FAN]);

        $this->actingAs($fan)
            ->withSession(['admin_authenticated_at' => now()->timestamp])
            ->get(route('admin.site-editor.show', ['page' => 'home']))
            ->assertForbidden();
    }

    public function test_admin_can_open_music_site_editor_by_default(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertSee('Reny Site Editor')
            ->assertSee('Banner')
            ->assertSee('Guardar y publicar');
    }

    public function test_music_album_cms_renders_upload_progress_controls(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertSee('data-album-upload-progress-form', false)
            ->assertSee(route('admin.content.album-track-audio.store'), false)
            ->assertSee('data-max-tracks="30"', false)
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('data-upload-progress', false)
            ->assertSee('data-upload-file-list', false)
            ->assertSee('data-upload-cancel', false)
            ->assertSee('data-upload-retry', false)
            ->assertSee('name="action" value="publish"', false)
            ->assertSee('name="action" value="draft"', false)
            ->assertSee('formActionUrl(form)', false)
            ->assertDontSee('form.action, true', false);
    }

    public function test_music_site_editor_manage_music_lists_edit_and_delete_actions(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $audio = $this->mediaAsset(MediaAssetType::Audio->value);
        $artwork = $this->mediaAsset(MediaAssetType::Thumbnail->value);
        $song = $this->musicContent(ContentType::Song, 'Manage Single', [
            'audio_asset_id' => $audio->id,
            'artwork_asset_id' => $artwork->id,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);
        $album = $this->musicContent(ContentType::MusicalAlbum, 'Manage Album', [
            'album_artwork_asset_id' => $artwork->id,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
            'tracks' => [
                [
                    'track_name' => 'Track One',
                    'track_audio_asset_id' => $audio->id,
                ],
            ],
        ]);
        $playlist = $this->musicContent(ContentType::MusicPlaylist, 'Manage Playlist', [
            'playlist_cover_asset_id' => $artwork->id,
            'tracks' => ['song:'.$song->id, 'album:'.$album->id.':0'],
        ]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'music']))
            ->assertOk()
            ->assertSee('Manage Music')
            ->assertSee('Manage Single')
            ->assertSee('Manage Album')
            ->assertSee('Manage Playlist')
            ->assertSee(route('admin.content.update', $song), false)
            ->assertSee(route('admin.content.update', $album), false)
            ->assertSee(route('admin.content.update', $playlist), false)
            ->assertSee(route('admin.content.destroy', $song), false)
            ->assertSee('Current audio kept if no new file is uploaded.')
            ->assertSee('Current artwork kept if no new file is uploaded.');
    }

    public function test_music_manage_edit_reuses_existing_assets_without_new_uploads(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $audio = $this->mediaAsset(MediaAssetType::Audio->value);
        $artwork = $this->mediaAsset(MediaAssetType::Thumbnail->value);
        $song = $this->musicContent(ContentType::Song, 'Original Single', [
            'audio_asset_id' => $audio->id,
            'artwork_asset_id' => $artwork->id,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);

        $this->actingAsAdmin($admin);

        $this->patch(route('admin.content.update', $song), [
            'return_to_music_editor' => '1',
            '_music_form_key' => 'music-songs-'.$song->id,
            'action' => 'publish',
            'type' => ContentType::Song->value,
            'title' => 'Edited Single',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'audio_asset_id' => $audio->id,
                'artwork_asset_id' => $artwork->id,
                'release_date_member_view' => '2026-07-03T10:00',
                'release_date_open_view' => '2026-07-04T10:00',
            ],
        ])
            ->assertRedirect(route('admin.site-editor.show', ['page' => 'music']));

        $song->refresh();

        $this->assertSame('Edited Single', $song->title);
        $this->assertSame($audio->id, data_get($song->metadata, 'audio_asset_id'));
        $this->assertSame($artwork->id, data_get($song->metadata, 'artwork_asset_id'));
    }

    public function test_music_manage_archives_published_music_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $song = $this->musicContent(ContentType::Song, 'Delete Me', [
            'audio_asset_id' => $this->mediaAsset(MediaAssetType::Audio->value)->id,
            'artwork_asset_id' => $this->mediaAsset(MediaAssetType::Thumbnail->value)->id,
            'release_date_member_view' => '2026-07-01T10:00',
            'release_date_open_view' => '2026-07-02T10:00',
        ]);

        $this->actingAsAdmin($admin);

        $this->post(route('admin.content.archive', $song))
            ->assertRedirect();

        $this->assertDatabaseHas('editorial_contents', [
            'id' => $song->id,
            'status' => EditorialStatus::Archived->value,
            'archived_by_id' => $admin->id,
        ]);
    }

    public function test_admin_site_editor_routes_stay_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'store']))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Reny Site Editor')
            ->assertDontSee('Preview publico')
            ->assertDontSee('CMS conectado');
    }

    public function test_site_editor_preview_stays_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.preview', ['page' => 'home']))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Guest')
            ->assertDontSee('Sign in');
    }

    public function test_store_preview_shows_royal_pass_banner_as_guest_for_authenticated_admin(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->get(route('admin.site-editor.preview', ['page' => 'store']))
            ->assertOk()
            ->assertSee('data-royal-pass-banner', false)
            ->assertSee('Royal Pass')
            ->assertSee('Guest')
            ->assertDontSee($admin->name);
    }

    public function test_unknown_site_editor_page_stays_on_enter_screen_when_cms_is_disabled(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->get(route('admin.site-editor.show', ['page' => 'not-real']))
            ->assertOk()
            ->assertSee('Enter');
    }

    private function actingAsAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }

    private function musicContent(ContentType $type, string $title, array $metadata): EditorialContent
    {
        return EditorialContent::factory()->create([
            'type' => $type->value,
            'title' => $title,
            'status' => EditorialStatus::Published->value,
            'visibility' => VisibilityAudience::Open->value,
            'published_at' => now(),
            'metadata' => $metadata,
        ]);
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

    private function actingAsParkedAdmin(User $user): void
    {
        config(['admin.cms_enabled' => false]);

        $this->actingAsAdmin($user);
    }
}
