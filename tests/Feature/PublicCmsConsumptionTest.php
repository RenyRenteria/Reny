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
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicCmsConsumptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_videos_page_uses_cms_content_and_cached_snapshot_survives_schema_outage(): void
    {
        EditorialContent::factory()->published()->create([
            'type' => ContentType::Video->value,
            'title' => 'CMS Premiere Video',
            'slug' => 'cms-premiere-video',
            'summary' => 'Published from admin CMS.',
            'visibility' => VisibilityAudience::Open->value,
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=Ue8orNrHw9s',
                'category' => 'music-video',
                'access_tier' => VisibilityAudience::Open->value,
            ],
        ]);

        $this->get('/videos')
            ->assertOk()
            ->assertSee('CMS Premiere Video')
            ->assertSee('Published from admin CMS.');

        $this->assertIsArray(Cache::get('public_cms.snapshots.videos.guest'));

        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('editorial_contents')
            ->andReturn(false);

        $this->get('/videos')
            ->assertOk()
            ->assertSee('CMS Premiere Video')
            ->assertSee('Published from admin CMS.');
    }

    public function test_member_visibility_blocks_guest_ui_and_backend_after_visibility_change(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Member-only studio note',
            'slug' => 'member-only-studio-note',
            'body' => 'Protected member payload.',
            'visibility' => VisibilityAudience::Open->value,
        ]);

        $this->get('/community')
            ->assertOk()
            ->assertSee('Member-only studio note');
        $this->assertIsArray(Cache::get('public_cms.snapshots.community.guest'));

        $this->get(route('content.show', ['type' => ContentType::Post->value, 'slug' => $content->slug]))
            ->assertOk()
            ->assertJsonPath('title', 'Member-only studio note');

        $content->forceFill([
            'visibility' => VisibilityAudience::Member->value,
        ])->save();

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Member-only studio note');
        $this->assertNull(Cache::get('public_cms.snapshots.community.guest'));

        Schema::partialMock()
            ->shouldReceive('hasTable')
            ->with('editorial_contents')
            ->andReturn(false);

        $this->get('/community')
            ->assertOk()
            ->assertDontSee('Member-only studio note');

        $this->get(route('content.show', ['type' => ContentType::Post->value, 'slug' => $content->slug]))
            ->assertForbidden();

        $royal = User::factory()->royal()->create();

        $this->actingAs($royal)
            ->get(route('content.show', ['type' => ContentType::Post->value, 'slug' => $content->slug]))
            ->assertOk()
            ->assertJsonPath('title', 'Member-only studio note');
    }

    public function test_purchased_content_survives_royal_expiration(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::DeluxeAlbum->value,
            'title' => 'Purchased Deluxe Vault',
            'slug' => 'purchased-deluxe-vault',
            'visibility' => VisibilityAudience::Purchased->value,
            'purchase_key' => 'deluxe-vault',
            'metadata' => [
                'tracklist' => "Track 1\nTrack 2",
                'narrative' => 'Purchased album narrative.',
                'release_cycle' => 'deluxe',
            ],
        ]);
        $user = User::factory()->expiredRoyal()->create();

        UserUnlock::create([
            'user_id' => $user->id,
            'unlock_type' => 'album',
            'product_key' => 'deluxe-vault',
            'title' => 'Purchased Deluxe Vault',
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
            'unlocked_at' => now()->subWeek(),
        ]);

        $this->assertFalse($user->fresh()->hasRoyalAccess());

        $this->get(route('content.show', ['type' => ContentType::DeluxeAlbum->value, 'slug' => $content->slug]))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('content.show', ['type' => ContentType::DeluxeAlbum->value, 'slug' => $content->slug]))
            ->assertOk()
            ->assertJsonPath('title', 'Purchased Deluxe Vault');
    }

    public function test_purchased_public_snapshots_are_scoped_per_user(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Video->value,
            'title' => 'Purchased Video Drop',
            'slug' => 'purchased-video-drop',
            'summary' => 'Buyer-only video.',
            'visibility' => VisibilityAudience::Purchased->value,
            'purchase_key' => 'video-drop',
            'metadata' => [
                'youtube_url' => 'https://www.youtube.com/watch?v=Ue8orNrHw9s',
                'category' => 'music-video',
                'access_tier' => VisibilityAudience::Purchased->value,
            ],
        ]);
        $buyer = User::factory()->create();
        $otherUser = User::factory()->create();

        UserUnlock::create([
            'user_id' => $buyer->id,
            'unlock_type' => 'video',
            'product_key' => 'video-drop',
            'title' => 'Purchased Video Drop',
            'source_type' => 'editorial_content',
            'source_id' => (string) $content->id,
            'status' => 'available',
            'unlocked_at' => now()->subDay(),
        ]);

        $this->actingAs($buyer)
            ->get('/videos')
            ->assertOk()
            ->assertSee('Purchased Video Drop');

        $this->assertIsArray(Cache::get("public_cms.snapshots.videos.user.{$buyer->id}"));
        $this->assertNull(Cache::get("public_cms.snapshots.videos.user.{$otherUser->id}"));

        $this->actingAs($otherUser)
            ->get('/videos')
            ->assertOk()
            ->assertDontSee('Purchased Video Drop');
    }

    public function test_archived_content_returns_not_found_without_breaking_public_page(): void
    {
        $content = EditorialContent::factory()->published()->create([
            'type' => ContentType::Post->value,
            'title' => 'Archived public note',
            'slug' => 'archived-public-note',
            'body' => 'Old payload.',
            'visibility' => VisibilityAudience::Open->value,
        ]);

        $content->forceFill([
            'status' => EditorialStatus::Archived->value,
            'archived_at' => now(),
        ])->save();

        $this->get(route('content.show', ['type' => ContentType::Post->value, 'slug' => $content->slug]))
            ->assertNotFound();

        $this->get('/community')
            ->assertOk()
            ->assertSee('Official Feed')
            ->assertDontSee('Archived public note');
    }
}
