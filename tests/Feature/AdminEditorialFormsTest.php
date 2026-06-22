<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\TaxonomyType;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\Taxonomy;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminEditorialFormsTest extends TestCase
{
    use RefreshDatabase;

    public function test_editor_can_prepare_one_draft_for_each_v1_content_type(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAsAdmin($editor);

        foreach (ContentType::values() as $type) {
            $this->postJson(route('admin.content.store'), $this->payloadForType($type))
                ->assertCreated()
                ->assertJsonPath('type', $type)
                ->assertJsonPath('status', EditorialStatus::Draft->value)
                ->assertJsonPath('needs_approval', true);
        }

        $this->assertSame(count(ContentType::cases()), EditorialContent::query()->count());
    }

    public function test_admin_can_publish_one_piece_for_each_v1_content_type(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        foreach (ContentType::values() as $type) {
            $this->postJson(route('admin.content.store'), [
                ...$this->payloadForType($type),
                'action' => 'publish',
                'title' => "Published {$type}",
            ])
                ->assertCreated()
                ->assertJsonPath('type', $type)
                ->assertJsonPath('status', EditorialStatus::Published->value)
                ->assertJsonPath('needs_approval', false);
        }

        foreach (ContentType::values() as $type) {
            $this->assertDatabaseHas('editorial_contents', [
                'type' => $type,
                'status' => EditorialStatus::Published->value,
                'needs_approval' => false,
                'published_by_id' => $admin->id,
            ]);
        }
    }

    public function test_editor_cannot_publish_or_schedule_from_form_endpoint(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $this->actingAsAdmin($editor);

        $this->postJson(route('admin.content.store'), [
            ...$this->payloadForType(ContentType::Post->value),
            'action' => 'publish',
        ])->assertForbidden();

        $this->postJson(route('admin.content.store'), [
            ...$this->payloadForType(ContentType::Post->value),
            'action' => 'schedule',
            'scheduled_at' => '2026-07-10T09:30',
        ])->assertForbidden();

        $this->assertDatabaseCount('editorial_contents', 0);
    }

    public function test_scheduled_date_is_stored_from_panama_timezone_and_not_visible_early(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $expectedUtc = CarbonImmutable::create(2026, 7, 10, 9, 30, 0, 'America/Panama')->utc();

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            ...$this->payloadForType(ContentType::Post->value),
            'action' => 'schedule',
            'scheduled_at' => '2026-07-10T09:30',
        ])
            ->assertCreated()
            ->assertJsonPath('status', EditorialStatus::Scheduled->value)
            ->assertJsonPath('scheduled_at', $expectedUtc->toISOString());

        $content = EditorialContent::query()->firstOrFail();
        $visibleAt = $expectedUtc->timezone(config('app.timezone', 'UTC'));

        $this->assertSame($expectedUtc->toISOString(), $content->scheduled_at->toISOString());
        $this->assertFalse(EditorialContent::visibleFor(null, $visibleAt->subSecond())->whereKey($content)->exists());
        $this->assertTrue(EditorialContent::visibleFor(null, $visibleAt->addSecond())->whereKey($content)->exists());
    }

    public function test_private_preview_requires_admin_session_and_stays_on_enter_screen(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $content = EditorialContent::factory()->create([
            'title' => 'Private preview candidate',
            'body' => 'Preview body.',
        ]);

        $this->get(route('admin.content.preview', $content))
            ->assertRedirect(route('admin.login'));

        $this->actingAsAdmin($admin);

        $this->get(route('admin.content.preview', $content))
            ->assertOk()
            ->assertSee('Enter')
            ->assertDontSee('Private preview candidate');
    }

    public function test_form_save_can_attach_reusable_media_and_taxonomy(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $asset = $this->mediaAsset(MediaAssetType::Image->value);
        $taxonomy = Taxonomy::create([
            'type' => TaxonomyType::Tag->value,
            'name' => 'Behind the scenes',
            'slug' => 'behind-the-scenes',
        ]);

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            ...$this->payloadForType(ContentType::Photo->value),
            'media_asset_ids' => [$asset->id],
            'taxonomy_ids' => [$taxonomy->id],
        ])->assertCreated();

        $content = EditorialContent::query()->firstOrFail();

        $this->assertTrue($content->mediaAssets()->whereKey($asset)->exists());
        $this->assertTrue($content->taxonomies()->whereKey($taxonomy)->exists());
    }

    public function test_type_specific_validation_rejects_incomplete_poll_payload(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsAdmin($admin);

        $this->postJson(route('admin.content.store'), [
            'action' => 'draft',
            'type' => ContentType::Poll->value,
            'title' => 'Incomplete poll',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'question' => 'Which song next?',
                'eligibility' => VisibilityAudience::Open->value,
                'results_visibility' => 'public',
            ],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('metadata.options');

        $this->assertDatabaseCount('editorial_contents', 0);
    }

    private function payloadForType(string $type): array
    {
        return [
            'action' => 'draft',
            'type' => $type,
            'title' => "Draft {$type}",
            'summary' => "Summary for {$type}",
            'body' => "Body for {$type}",
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => in_array($type, [ContentType::DeluxeAlbum->value, ContentType::Product->value], true)
                ? "purchase-{$type}"
                : null,
            'metadata' => $this->metadataForType($type),
        ];
    }

    private function metadataForType(string $type): array
    {
        return match ($type) {
            ContentType::Song->value => [
                'audio_asset_id' => $this->mediaAsset(MediaAssetType::Audio->value)->id,
                'artwork_asset_id' => $this->mediaAsset(MediaAssetType::Thumbnail->value)->id,
                'release_date_member_view' => '2026-07-01T10:00',
                'release_date_open_view' => '2026-07-02T10:00',
            ],
            ContentType::MusicalAlbum->value => [
                'album_artwork_asset_id' => $this->mediaAsset(MediaAssetType::Thumbnail->value)->id,
                'release_date_member_view' => '2026-07-01T10:00',
                'release_date_open_view' => '2026-07-02T10:00',
                'tracks' => [
                    [
                        'track_name' => 'Track 1',
                        'track_audio_asset_id' => $this->mediaAsset(MediaAssetType::Audio->value)->id,
                    ],
                ],
            ],
            ContentType::DeluxeAlbum->value => [
                'package_title' => 'Deluxe package',
                'package_notes' => 'Album story.',
                'price' => 12.99,
            ],
            ContentType::MusicPlaylist->value => [
                'playlist_cover_asset_id' => $this->mediaAsset(MediaAssetType::Thumbnail->value)->id,
                'tracks' => [$this->firstMusicTrackReference()],
            ],
            ContentType::Video->value => [
                'youtube_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
                'category' => 'music-video',
                'access_tier' => VisibilityAudience::Open->value,
                'playlist' => 'official',
            ],
            ContentType::Photo->value => [
                'caption' => 'Backstage photo.',
                'location' => 'Panama',
                'tags' => 'backstage,live',
            ],
            ContentType::Gallery->value => [
                'image_count' => 4,
                'caption' => 'Tour gallery.',
                'location' => 'Panama',
                'tags' => 'tour',
            ],
            ContentType::Post->value => [
                'links' => 'https://renyrenteria.com',
                'comments_enabled' => true,
                'is_pinned' => false,
            ],
            ContentType::Poll->value => [
                'question' => 'Which song should drop next?',
                'options' => ['Song A', 'Song B'],
                'eligibility' => VisibilityAudience::Open->value,
                'results_visibility' => 'public',
            ],
            ContentType::Product->value => [
                'product_kind' => 'digital',
                'sku' => 'DIGI-001',
                'price_cents' => 999,
                'inventory' => 100,
            ],
            ContentType::Event->value => [
                'event_kind' => 'listening_session',
                'starts_at' => '2026-08-12T20:00',
                'ticketing_mode' => 'rsvp',
                'location' => 'Panama City',
                'inventory' => 80,
            ],
            ContentType::Drop->value => [
                'drop_kind' => 'bundle',
                'opens_at' => '2026-09-01T10:00',
                'closes_at' => '2026-09-02T10:00',
                'inventory' => 50,
            ],
            ContentType::Exclusive->value => [
                'exclusive_kind' => 'download',
                'access_note' => 'Royal members only.',
                'expires_at' => '2026-12-01T10:00',
            ],
            default => [],
        };
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

    private function firstMusicTrackReference(): string
    {
        $songId = EditorialContent::query()
            ->where('type', ContentType::Song->value)
            ->value('id');

        return 'song:'.$songId;
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
