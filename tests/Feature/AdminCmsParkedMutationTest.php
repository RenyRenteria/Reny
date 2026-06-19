<?php

namespace Tests\Feature;

use App\Enums\ContentType;
use App\Enums\EditorialStatus;
use App\Enums\MediaAssetType;
use App\Enums\VisibilityAudience;
use App\Models\EditorialContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminCmsParkedMutationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_content_and_editorial_create_routes_stay_on_enter_without_creating_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->postJson(route('admin.content.store'), $this->contentPayload())
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.editorial.drafts.store'), $this->contentPayload())
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.editorial.publish'), $this->contentPayload())
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.editorial.schedule'), [
            ...$this->contentPayload(),
            'scheduled_at' => '2026-07-01T09:30',
        ])
            ->assertOk()
            ->assertSee('Enter');

        $this->assertDatabaseCount('editorial_contents', 0);
    }

    public function test_admin_content_and_editorial_update_routes_stay_on_enter_without_changing_content(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $content = EditorialContent::factory()->create([
            'title' => 'Original title',
            'status' => EditorialStatus::Draft->value,
            'needs_approval' => true,
        ]);

        $this->actingAsParkedAdmin($admin);

        $this->putJson(route('admin.content.update', $content), [
            'title' => 'Changed through content route',
        ])
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.editorial.update', $content), [
            'title' => 'Changed through editorial route',
        ])
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.editorial.publish'), [
            'content_id' => $content->id,
            'title' => 'Published through parked route',
        ])
            ->assertOk()
            ->assertSee('Enter');

        $content = $content->fresh();

        $this->assertSame('Original title', $content->title);
        $this->assertSame(EditorialStatus::Draft, $content->status);
        $this->assertTrue($content->needs_approval);
        $this->assertNull($content->published_by_id);
    }

    public function test_admin_media_routes_stay_on_enter_without_creating_media(): void
    {
        Storage::fake('public');
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->post(route('admin.media.store'), [
            'type' => MediaAssetType::Image->value,
            'title' => 'Cover art',
            'alt_text' => 'Reny cover art portrait',
            'is_public' => true,
            'file' => UploadedFile::fake()->image('cover.jpg')->size(512),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertSee('Enter');

        $this->postJson(route('admin.media.mux.direct-uploads.store'), [
            'title' => 'Behind the scenes',
            'original_filename' => 'behind-the-scenes.mp4',
            'mime_type' => 'video/mp4',
            'size_bytes' => 1024,
            'duration_seconds' => 600,
            'is_public' => true,
        ])
            ->assertOk()
            ->assertSee('Enter');

        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_music_banner_route_stays_on_enter_without_changing_site_settings(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        $this->actingAsParkedAdmin($admin);

        $this->post(route('admin.site-editor.music-banner.update'), [
            'action' => 'publish',
            'eyebrow_line_1' => 'Draft',
            'eyebrow_line_2' => 'Banner',
            'title_line_1' => 'Parked',
            'title_line_2' => 'Music',
            'subtitle' => 'Should not save',
            'description' => 'This should not persist while the CMS is parked.',
            'footer_line_1' => 'Visit us today at',
            'footer_line_2' => 'renyrenteria.com',
            'badge' => 'RR',
            'destination_url' => 'https://renyrenteria.com',
            'sticker_line_1' => 'THE FIRST ALBUM',
            'sticker_line_2' => 'BANO #1',
            'status' => 'published',
        ])
            ->assertOk()
            ->assertSee('Enter');

        $this->assertDatabaseCount('site_page_settings', 0);
    }

    private function contentPayload(): array
    {
        return [
            'type' => ContentType::Post->value,
            'title' => 'Parked CMS content',
            'summary' => 'This should not persist while the CMS is parked.',
            'body' => 'Parked content body.',
            'visibility' => VisibilityAudience::Open->value,
        ];
    }

    private function actingAsParkedAdmin(User $user): void
    {
        $this->actingAs($user)->withSession([
            'admin_authenticated_at' => now()->timestamp,
        ]);
    }
}
