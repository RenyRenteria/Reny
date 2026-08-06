<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialAuditAction;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\MediaProcessingStatus;
use App\Enums\TaxonomyType;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\MediaAsset;
use App\Models\Taxonomy;
use App\Models\User;
use App\Models\UserUnlock;
use App\Services\EditorialWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditorialDomainWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_content_type_can_be_created_as_draft(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);
        $asset = $this->readyAsset();

        $this->actingAsAdmin($editor);

        foreach (ContentType::cases() as $type) {
            $this->postJson(route('admin.editorial.drafts.store'), $this->payloadFor($type, $asset))
                ->assertOk()
                ->assertJsonPath('type', $type->value)
                ->assertJsonPath('status', EditorialStatus::Draft->value)
                ->assertJsonPath('needs_approval', true);
        }

        $this->assertSame(count(ContentType::cases()), EditorialContent::query()->count());

        foreach (ContentType::values() as $type) {
            $this->assertDatabaseHas('editorial_contents', [
                'type' => $type,
                'status' => EditorialStatus::Draft->value,
                'needs_approval' => true,
                'created_by_id' => $editor->id,
            ]);
        }
    }

    public function test_scheduled_content_does_not_resolve_before_release_date(): void
    {
        $tomorrow = now()->addDay();

        $content = EditorialContent::factory()->create([
            'status' => EditorialStatus::Scheduled->value,
            'visibility' => VisibilityAudience::Open->value,
            'scheduled_at' => $tomorrow,
        ]);

        $content->releaseWindows()->create([
            'audience' => VisibilityAudience::Open->value,
            'starts_at' => now()->subMinute(),
        ]);

        $this->assertFalse($content->fresh('releaseWindows')->isVisibleTo(null, now()));
        $this->assertTrue($content->fresh('releaseWindows')->isVisibleTo(null, $tomorrow->copy()->addMinute()));

        $this->assertFalse(EditorialContent::visibleFor(null, now())->whereKey($content)->exists());
        $this->assertTrue(EditorialContent::visibleFor(null, $tomorrow->copy()->addMinute())->whereKey($content)->exists());
    }

    public function test_audience_release_windows_are_evaluated_by_access_state(): void
    {
        $now = now();
        $openDate = $now->copy()->addWeek();
        $royalUser = User::factory()->royal()->create();

        $content = EditorialContent::factory()->published()->create([
            'visibility' => VisibilityAudience::Royal->value,
        ]);

        $content->releaseWindows()->createMany([
            [
                'audience' => VisibilityAudience::Member->value,
                'starts_at' => $now,
            ],
            [
                'audience' => VisibilityAudience::Open->value,
                'starts_at' => $openDate,
            ],
        ]);

        $content = $content->fresh('releaseWindows');

        $this->assertFalse($content->isVisibleTo(null, $now));
        $this->assertTrue($content->isVisibleTo($royalUser, $now));
        $this->assertTrue($content->isVisibleTo(null, $openDate->copy()->addMinute()));
    }

    public function test_purchased_visibility_survives_expired_royal_access(): void
    {
        $expiredRoyal = User::factory()->expiredRoyal()->create();

        $content = EditorialContent::factory()->published()->create([
            'visibility' => VisibilityAudience::Purchased->value,
            'purchase_key' => 'deluxe-drop-001',
        ]);

        $this->assertFalse($content->isVisibleTo($expiredRoyal));

        UserUnlock::create([
            'user_id' => $expiredRoyal->id,
            'unlock_type' => 'content',
            'product_key' => 'deluxe-drop-001',
            'title' => $content->title,
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        $this->assertTrue($content->fresh()->isVisibleTo($expiredRoyal));
        $this->assertTrue(EditorialContent::visibleFor($expiredRoyal)->whereKey($content)->exists());
    }

    public function test_purchased_release_window_uses_content_or_product_unlocks(): void
    {
        $user = User::factory()->expiredRoyal()->create();
        $content = EditorialContent::factory()->published()->create([
            'visibility' => VisibilityAudience::Open->value,
            'purchase_key' => 'release-window-purchase',
        ]);

        $content->releaseWindows()->create([
            'audience' => VisibilityAudience::Purchased->value,
            'starts_at' => now()->subMinute(),
        ]);

        $this->assertFalse(EditorialContent::visibleFor($user)->whereKey($content)->exists());

        UserUnlock::create([
            'user_id' => $user->id,
            'unlock_type' => 'content',
            'product_key' => 'release-window-purchase',
            'title' => $content->title,
            'source_type' => 'order',
            'source_id' => 'order-123',
            'status' => 'available',
            'unlocked_at' => now(),
        ]);

        $this->assertTrue(EditorialContent::visibleFor($user)->whereKey($content)->exists());
    }

    public function test_taxonomy_can_be_reused_by_editorial_content(): void
    {
        $content = EditorialContent::factory()->create();

        $taxonomies = collect([
            ['type' => TaxonomyType::Category->value, 'name' => 'Music', 'slug' => 'music'],
            ['type' => TaxonomyType::Tag->value, 'name' => 'Behind the scenes', 'slug' => 'behind-the-scenes'],
            ['type' => TaxonomyType::Campaign->value, 'name' => 'Summer drop', 'slug' => 'summer-drop'],
            ['type' => TaxonomyType::Country->value, 'name' => 'Panama', 'slug' => 'panama', 'country_code' => 'PA'],
        ])->map(fn (array $attributes): Taxonomy => Taxonomy::create($attributes));

        $content->taxonomies()->attach($taxonomies->pluck('id'));

        $this->assertSame(4, $content->fresh('taxonomies')->taxonomies->count());
        $this->assertDatabaseHas('taxonomies', [
            'type' => TaxonomyType::Country->value,
            'country_code' => 'PA',
        ]);
    }

    public function test_editorial_audit_records_actor_action_and_timestamp(): void
    {
        $editor = User::factory()->create(['role' => User::ROLE_EDITOR]);

        $content = app(EditorialWorkflowService::class)->createDraft($editor, [
            'type' => ContentType::Post->value,
            'title' => 'Audit trail draft',
        ]);

        $log = $content->auditLogs()
            ->where('action', EditorialAuditAction::Created->value)
            ->firstOrFail();

        $this->assertSame($editor->id, $log->actor_id);
        $this->assertSame(EditorialAuditAction::Created, $log->action);
        $this->assertNotNull($log->created_at);

        $this->assertTrue($content->auditLogs()
            ->where('action', EditorialAuditAction::ApprovalRequested->value)
            ->exists());
    }

    private function readyAsset(string $type = MediaAssetType::Image->value): MediaAsset
    {
        $isAudio = $type === MediaAssetType::Audio->value;

        return MediaAsset::create([
            'type' => $type,
            'title' => 'Reusable editorial asset',
            'disk' => 'public',
            'path' => $isAudio ? 'media/editorial.mp3' : 'media/editorial.jpg',
            'original_filename' => $isAudio ? 'editorial.mp3' : 'editorial.jpg',
            'mime_type' => $isAudio ? 'audio/mpeg' : 'image/jpeg',
            'extension' => $isAudio ? 'mp3' : 'jpg',
            'size_bytes' => 1024,
            'is_public' => true,
            'alt_text' => $isAudio ? null : 'Reusable editorial asset',
            'processing_status' => MediaProcessingStatus::Ready->value,
        ]);
    }

    private function payloadFor(ContentType $type, MediaAsset $asset): array
    {
        $base = [
            'type' => $type->value,
            'title' => "Draft {$type->value}",
            'summary' => 'Draft prepared for editorial workflow.',
            'visibility' => VisibilityAudience::Royal->value,
            'media_asset_ids' => [$asset->id],
        ];

        return match ($type) {
            ContentType::Song => [
                ...$base,
                'metadata' => [
                    'audio_asset_id' => $this->readyAsset(MediaAssetType::Audio->value)->id,
                    'artwork_asset_id' => $this->readyAsset(MediaAssetType::Thumbnail->value)->id,
                    'release_date_member_view' => '2026-07-01T10:00',
                    'release_date_open_view' => '2026-07-02T10:00',
                ],
            ],
            ContentType::MusicalAlbum => [
                ...$base,
                'metadata' => [
                    'album_artwork_asset_id' => $this->readyAsset(MediaAssetType::Thumbnail->value)->id,
                    'release_date_member_view' => '2026-07-01T10:00',
                    'release_date_open_view' => '2026-07-02T10:00',
                    'tracks' => [
                        [
                            'track_name' => 'Intro',
                            'track_audio_asset_id' => $this->readyAsset(MediaAssetType::Audio->value)->id,
                        ],
                    ],
                ],
            ],
            ContentType::DeluxeAlbum => [
                ...$base,
                'purchase_key' => 'deluxe-draft',
                'metadata' => [
                    'package_title' => 'Deluxe draft',
                    'package_notes' => 'Draft notes',
                ],
            ],
            ContentType::MusicPlaylist => [
                ...$base,
                'metadata' => [
                    'playlist_cover_asset_id' => $this->readyAsset(MediaAssetType::Thumbnail->value)->id,
                    'tracks' => [$this->firstMusicTrackReference()],
                ],
            ],
            ContentType::Video => [
                ...$base,
                'metadata' => [
                    'video_url' => 'https://www.youtube.com/watch?v=abc12345678',
                    'category' => 'music',
                ],
            ],
            ContentType::Photo => [
                ...$base,
                'metadata' => [
                    'caption' => 'Photo caption',
                ],
            ],
            ContentType::Gallery => [
                ...$base,
                'metadata' => [
                    'gallery_theme' => 'Gallery theme',
                ],
            ],
            ContentType::Post => [
                ...$base,
                'body' => 'Post body.',
            ],
            ContentType::Poll => [
                ...$base,
                'metadata' => [
                    'question' => 'Poll question',
                    'options' => ['First', 'Second'],
                    'eligibility' => 'royal',
                ],
            ],
            ContentType::Product => [
                ...$base,
                'purchase_key' => 'product-draft',
                'metadata' => [
                    'product_type' => 'digital',
                    'price' => 9,
                ],
            ],
            ContentType::Event => [
                ...$base,
                'metadata' => [
                    'event_type' => 'listening session',
                    'event_starts_at' => '2026-08-01T20:00',
                    'venue' => 'Panama City',
                    'inventory' => 100,
                ],
            ],
            ContentType::Drop => [
                ...$base,
                'metadata' => [
                    'drop_window' => '2026-08-15T09:00',
                    'inventory' => 250,
                    'bundle_notes' => 'Drop notes',
                ],
            ],
            ContentType::Exclusive => [
                ...$base,
                'metadata' => [
                    'access_note' => 'Royal-only',
                    'unlocked_by' => 'Royal Pass',
                ],
            ],
        };
    }

    private function firstMusicTrackReference(): string
    {
        return 'song:'.EditorialContent::query()
            ->where('type', ContentType::Song->value)
            ->value('id');
    }

    private function actingAsAdmin(User $user): void
    {
        config(['admin.cms_enabled' => true]);

        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
